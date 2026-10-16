<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Models\User;
use App\Modules\Doctor\Application\Contracts\ClinicalReopenGuard;
use App\Modules\ImageGateway\Application\Contracts\RadiographerStudySource;
use App\Modules\Operator\Application\Contracts\RadiographerAccess;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Authorization\AdminPanelAccessService;
use App\Shared\Authorization\AuthorizationClaimResolver;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Identity\LocalId;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

final class RadiographerWorkspace implements RadiographerAccess
{
    public const CHECKS = ['identity', 'positioning', 'coverage', 'orientation', 'exposure', 'artifacts', 'completeness'];

    private int $depth = 0;

    private function user(): User
    {
        $id = Auth::id();
        $u = $id ? User::query()->whereKey($id)->lockForUpdate()->first() : null;
        if (! $u || ! $u->canAuthenticate()) {
            throw new DomainException('access_denied');
        }

        return $u;
    }

    private function eligible(string $user, string $site): void
    {
        $u = User::query()->whereKey($user)->lockForUpdate()->first();
        $s = DB::table('operator_sites')->where('id', $site)->lockForUpdate()->first();
        $e = DB::table('operator_radiographer_eligibility')->where('user_id', $user)->where('site_id', $site)->orderByDesc('revision')->lockForUpdate()->first();
        if (! $u || ! $u->canAuthenticate() || ! $s || ! $s->active || ! $e || $e->status !== 'verified'
            || ! $e->evidence_reference || ! $e->verifying_authority || new DateTimeImmutable($e->verified_at) > app(Clock::class)->now()) {
            throw new DomainException('access_denied');
        }
    }

    private function admin(string $action, ?string $site = null): User
    {
        $u = $this->user();
        // Lock claim rows so ordinary permission revocation and this operation serialize.
        DB::table('authorization_role_assignments')->where('user_id', $u->id)->lockForUpdate()->get();
        DB::table('authorization_permission_assignments')->where('user_id', $u->id)->lockForUpdate()->get();
        if (! in_array($action, ['issue', 'revoke', 'reassign', 'reopen', 'read'], true)
            || ! app(AdminPanelAccessService::class)->canAccess($u, 'admin')
            || ($action === 'read' && ! $this->managementAvailable())
            || ($action !== 'read' && ! in_array('operator.radiographer-workspace.'.$action, app(AuthorizationClaimResolver::class)->permissions($u), true))) {
            throw new DomainException('access_denied');
        }
        if ($site !== null && ! DB::table('operator_workspace_managers')->where('user_id', $u->id)->where('site_id', $site)->where('active', true)->lockForUpdate()->first()) {
            throw new DomainException('access_denied');
        }

        return $u;
    }

    public function authorizeManager(?string $site = null): void
    {
        $this->admin('read', $site);
    }

    private function authorized(string $workspace): object
    {
        if (DB::transactionLevel() < 1) {
            throw new DomainException('access_denied');
        }
        $u = $this->user();
        $w = DB::table('operator_radiographer_workspaces')->where('id', $workspace)->lockForUpdate()->first();
        $now = app(Clock::class)->now();
        if (! $w || $w->user_id !== $u->id || $w->revoked || new DateTimeImmutable($w->starts_at) > $now || new DateTimeImmutable($w->expires_at) <= $now) {
            throw new DomainException('access_denied');
        }
        $this->eligible($u->id, $w->site_id);
        $a = DB::table('operator_queue_admissions')->where('id', $w->admission_id)->lockForUpdate()->first();
        $t = $a ? DB::table('operator_paper_tickets')->where('id', $a->operator_paper_ticket_id)->lockForUpdate()->first() : null;
        if (! $a || ! $t || $a->stage !== 'xray' || $a->operator_site_id !== $w->site_id || $t->booking_id !== $w->booking_id
            || $t->operator_site_id !== $w->site_id || $t->member_schedule_id !== $a->member_schedule_id) {
            throw new DomainException('access_denied');
        }
        $w->member_schedule_id = $a->member_schedule_id;
        $w->protocol = DB::table('operator_workspace_protocols')->where('study_id', $w->study_id)->orderByDesc('revision')->lockForUpdate()->first();

        return $w;
    }

    public function run(string $workspace, string $action, callable $operation, int $generation): mixed
    {
        if (DB::transactionLevel() !== 0 && $this->depth === 0) {
            throw new DomainException('unavailable');
        }
        $this->depth++;
        try {
            return DB::transaction(function () use ($workspace, $action, $operation, $generation) {
                $w = $this->authorized($workspace);
                if ($w->generation !== $generation) {
                    throw new DomainException('access_denied');
                }
                $value = $operation($w);
                $fresh = $this->authorized($workspace);
                if ($fresh->generation !== $w->generation) {
                    throw new DomainException('access_denied');
                }
                $this->audit($fresh, $action);
                if ($this->authorized($workspace)->generation !== $w->generation) {
                    throw new DomainException('access_denied');
                }

                return $value;
            });
        } catch (Throwable $e) {
            $this->rollbackFailedCommit();
            $this->denied();
            throw new DomainException('workspace_unavailable');
        } finally {
            $this->depth--;
        }
    }

    private function audit(?object $w, string $action, string $outcome = 'success'): void
    {
        $c = app(AuthenticatedContextProvider::class)->current();
        $context = new AuthenticatedContext(actorId: $c->actorId, operationId: $c->operationId, sessionId: $c->sessionId,
            roles: $c->roles, permissions: $c->permissions, siteId: $w ? LocalId::fromString($w->site_id) : null,
            caseId: $w ? LocalId::fromString($w->booking_id) : null, purpose: 'radiographer.workspace');
        app(AuditStore::class)->append(AuditEvent::fromContext($context, 'radiographer-workspace.'.$action, 'operator', $outcome, app(Clock::class)->now(), targetType: 'radiographer-workspace', targetId: $w?->id,
            metadata: $w ? ['generation' => (int) $w->generation, 'revision' => (int) $w->revision, 'cycle' => (int) $w->cycle] : []));
    }

    private function rollbackFailedCommit(): void
    {
        // Laravel may clear its transaction counter when a pre-commit listener fails.
        if (DB::transactionLevel() === 0 && DB::connection()->getPdo()->inTransaction()) {
            DB::connection()->getPdo()->rollBack();
        }
    }

    public function denied(): void
    {
        $this->rollbackFailedCommit();
        try {
            $this->audit(null, 'denied', 'rejected');
        } catch (Throwable) {
            throw new DomainException('workspace_unavailable');
        }
    }

    private function history(object $w, string $action, #[\SensitiveParameter] array $data): void
    {
        DB::table('operator_radiographer_history')->insert(['id' => (string) Str::uuid(), 'workspace_id' => $w->id, 'actor_id' => Auth::id(), 'action' => $action, 'generation' => $w->generation, 'revision' => $w->revision, 'cycle' => $w->cycle, 'data' => json_encode($data, JSON_THROW_ON_ERROR), 'occurred_at' => app(Clock::class)->now()]);
    }

    public function managementAvailable(): bool
    {
        $u = Auth::user();
        if (! $u instanceof User || ! $u->canAuthenticate()) {
            return false;
        }
        $permissions = app(AuthorizationClaimResolver::class)->permissions($u);

        return array_intersect(array_map(fn ($a) => 'operator.radiographer-workspace.'.$a, ['issue', 'revoke', 'reassign', 'reopen']), $permissions) !== [];
    }

    public function indicator(string $reference): string
    {
        try {
            return $this->manage($reference, 'status', [])['label'];
        } catch (Throwable) {
            return 'Status ruang kerja tidak tersedia';
        }
    }

    private function candidates(string $site): array
    {
        $ids = DB::table('operator_radiographer_eligibility')->where('site_id', $site)->distinct()->limit(100)->pluck('user_id');
        $result = [];
        foreach ($ids as $id) {
            try {
                $this->eligible($id, $site);
            } catch (DomainException) {
                continue;
            }
            $result[] = ['id' => $id, 'label' => User::query()->whereKey($id)->value('email')];
        }

        return $result;
    }

    public function manage(string $reference, string $action, #[\SensitiveParameter] array $input): array
    {
        if (DB::transactionLevel() !== 0) {
            throw new DomainException('workspace_unavailable');
        }
        try {
            return DB::transaction(function () use ($reference, $action, $input) {
                $this->admin($action === 'status' ? 'read' : $action);
                $source = app(RadiographerStudySource::class)->selection($reference, false);
                if (! $source) {
                    throw new DomainException('access_denied');
                }
                $this->admin($action === 'status' ? 'read' : $action, $source->site_id);
                // Existing case serialization precedes Gateway source locks, as in clinical access.
                $discovered = DB::table('operator_radiographer_workspaces')->where('study_id', $source->study_id)->first();
                if ($discovered) {
                    DB::table('operator_radiographer_workspaces')->where('id', $discovered->id)->lockForUpdate()->first();
                }
                $current = app(RadiographerStudySource::class)->selection($reference);
                if ((array) $current !== (array) $source) {
                    throw new DomainException('source_changed');
                }
                $w = DB::table('operator_radiographer_workspaces')->where('study_id', $source->study_id)->lockForUpdate()->first();
                if ($action === 'status') {
                    if (! $w) {
                        return ['label' => 'Belum ditugaskan'];
                    }
                    $this->audit($w, 'read');
                    $qc = $w->qc ? json_decode($w->qc, true, flags: JSON_THROW_ON_ERROR) : null;
                    $label = $w->revoked ? 'Akses dicabut' : (new DateTimeImmutable($w->expires_at) <= app(Clock::class)->now() ? 'Akses kedaluwarsa' : 'Ditugaskan');

                    return ['label' => $label.' · '.($w->active_handoff ? 'Menunggu tinjauan radiolog' : ($qc['outcome'] ?? 'QC belum direkam'))];
                }
                if ($action === 'read') {
                    if (! $w) {
                        $this->audit((object) ['id' => null, 'site_id' => $source->site_id, 'booking_id' => $source->booking_id, 'generation' => 0, 'revision' => 0, 'cycle' => 0], 'read');

                        return ['state' => 'unassigned', 'access_status' => 'unassigned', 'candidates' => $this->candidates($source->site_id)];
                    }
                    $this->audit($w, 'read');

                    $candidates = $this->candidates($source->site_id);
                    $eligible = true;
                    try {
                        $this->eligible($w->user_id, $w->site_id);
                    } catch (DomainException) {
                        $eligible = false;
                    }
                    $now = app(Clock::class)->now();
                    $access = $w->revoked ? 'revoked' : (new DateTimeImmutable($w->expires_at) <= $now ? 'expired' : (new DateTimeImmutable($w->starts_at) > $now || ! $eligible ? 'unavailable' : 'active'));

                    return ['access_status' => $access, 'assignee' => User::query()->whereKey($w->user_id)->value('email'), 'state' => $w->active_handoff ? 'pending_radiologist_review' : 'unreviewed', 'id' => $w->id, 'generation' => $w->generation, 'expires_at' => $w->expires_at, 'revoked' => (bool) $w->revoked, 'handoff' => $w->active_handoff, 'candidates' => $candidates];
                }
                if (in_array($action, ['issue', 'reassign'], true)) {
                    $input += ['hours' => 24];
                    $this->validate($input, ['user' => 'required|string|max:36', 'hours' => 'required|integer|in:24,72,168']);
                    $this->eligible($input['user'], $source->site_id);
                    if (($action === 'issue' && $w) || ($action === 'reassign' && ! $w)) {
                        throw new DomainException('conflict');
                    }
                    $id = $w?->id ?? (string) Str::uuid();
                    $now = app(Clock::class)->now();
                    $values = ['user_id' => $input['user'], 'generation' => $w ? $w->generation + 1 : 1, 'starts_at' => $now, 'expires_at' => $now->modify('+'.$input['hours'].' hours'), 'revoked' => false];
                    if ($w) {
                        DB::table('operator_radiographer_workspaces')->where('id', $id)->update($values);
                    } else {
                        DB::table('operator_radiographer_workspaces')->insert($values + ['id' => $id, 'study_id' => $source->study_id, 'booking_id' => $source->booking_id, 'admission_id' => $source->admission_id, 'site_id' => $source->site_id]);
                    }
                    $w = DB::table('operator_radiographer_workspaces')->where('id', $id)->first();
                    $event = $action === 'issue' ? 'issued' : 'reassigned';
                    $this->history($w, $event, ['user' => $w->user_id, 'expires_at' => $w->expires_at]);
                    $this->audit($w, $event);

                    return ['id' => $id, 'generation' => $w->generation, 'url' => URL::temporarySignedRoute('radiographer.entry', new DateTimeImmutable($w->expires_at), ['workspace' => $id, 'generation' => $w->generation])];
                }
                if (! $w) {
                    throw new DomainException('conflict');
                }
                if ($action === 'revoke') {
                    $this->validate($input, []);
                    DB::table('operator_radiographer_workspaces')->where('id', $w->id)->update(['revoked' => true, 'generation' => $w->generation + 1]);
                } elseif ($action === 'reopen') {
                    $this->validate($input, ['reason' => 'required|string|max:500', 'handoff' => 'required|uuid']);
                    if (! $w->active_handoff || $w->active_handoff !== $input['handoff'] || ! $w->binding) {
                        throw new DomainException('conflict');
                    }
                    app(ClinicalReopenGuard::class)->permitReopen($w->booking_id, $w->binding, $w->active_handoff);
                    DB::table('operator_radiographer_workspaces')->where('id', $w->id)->update(['active_handoff' => null, 'cycle' => $w->cycle + 1, 'revision' => $w->revision + 1, 'qc' => null]);
                } else {
                    throw new DomainException('access_denied');
                }
                $w = DB::table('operator_radiographer_workspaces')->where('id', $w->id)->first();
                $event = $action === 'revoke' ? 'revoked' : 'reopened';
                $this->history($w, $event, $input);
                $this->audit($w, $event);

                return ['id' => $w->id];
            });
        } catch (Throwable) {
            $this->rollbackFailedCommit();
            $this->denied();
            throw new DomainException('workspace_unavailable');
        }
    }

    public function entry(string $workspace, int $generation): string
    {
        return $this->run($workspace, 'read', function ($w) use ($generation) {
            if ($w->generation !== $generation) {
                throw new DomainException('access_denied');
            }

            return $w->id;
        }, $generation);
    }

    public function read(string $workspace, int $generation): array
    {
        return $this->run($workspace, 'read', function ($w) {
            $source = app(RadiographerStudySource::class)->inspect($w->id, $w->generation);
            $autofill = [];
            foreach (['studyDescription', 'bodyPart', 'projection', 'laterality', 'detectorType'] as $field) {
                $origin = $source['source']['technical_fields'][$field] ?? null;
                $autofill[$field] = ['value' => $field === 'detectorType' ? ($origin['value'] ?? null) : ($source['actual'][$field] ?? null), 'authority' => $origin ? $origin['authority'] : (! empty($source['actual'][$field]) ? 'embedded-observed' : 'unknown'), 'source_reference' => $origin['source_reference'] ?? $source['binding'], 'source_value' => $origin['value'] ?? null];
            }

            return ['display_reference' => $source['display_reference'], 'actual' => $source['actual'], 'identity' => $source['identity'], 'source' => $source['source'], 'binding' => $source['binding'], 'autofill' => $autofill,
                'overlay' => $w->overlay ? json_decode($w->overlay, true, flags: JSON_THROW_ON_ERROR) : null,
                'qc' => $w->qc ? json_decode($w->qc, true, flags: JSON_THROW_ON_ERROR) : null,
                'revision' => $w->revision, 'cycle' => $w->cycle, 'handoff' => $w->active_handoff, 'read_only' => $w->active_handoff !== null,
                'protocol' => $source['protocol'] ? array_intersect_key($source['protocol'], array_flip(['reference', 'revision', 'projection', 'description', 'approved', 'complete'])) : null,
                'source_changed' => $w->binding !== null && $w->binding !== $source['binding'], 'expires_at' => $w->expires_at];
        }, $generation);
    }

    public function dicom(string $workspace, int $generation): string
    {
        return app(RadiographerStudySource::class)->inspect($workspace, $generation)['bytes'];
    }

    public function save(string $workspace, #[\SensitiveParameter] array $input, int $generation): array
    {
        return $this->run($workspace, 'metadata-saved', function ($w) use ($input) {
            $this->validate($input, ['revision' => 'required|integer|min:0', 'binding' => 'required|string|size:64', 'overlay' => 'required|array', 'qc' => 'required|array']);
            if ($w->active_handoff || (int) $input['revision'] !== $w->revision) {
                throw new DomainException('conflict');
            }
            $this->validate($input['overlay'], ['studyDescription' => 'nullable|string|max:64', 'bodyPart' => 'nullable|string|in:CHEST', 'projection' => 'nullable|string|in:PA,AP', 'laterality' => 'nullable|string|in:R,L,B,not_applicable,unknown', 'detectorType' => 'nullable|string|in:BED,TRX,THORAX', 'reason' => 'required|string|max:500']);
            $this->validate($input['qc'], ['outcome' => 'required|string|in:Layak,Tidak Layak,Perlu Eskalasi', 'checks' => 'required|array', 'rendered' => 'required|boolean', 'reason' => 'nullable|string|max:1000']);
            $checks = $input['qc']['checks'];
            if (array_diff(array_keys($checks), self::CHECKS) || array_diff(self::CHECKS, array_keys($checks))) {
                throw new DomainException('invalid_checks');
            }
            foreach ($checks as $key => $value) {
                if (! in_array($value, [true, false, '1', '0', 1, 0], true)) {
                    throw new DomainException('invalid_checks');
                }
                $checks[$key] = in_array($value, [true, '1', 1], true);
            }
            $qc = $input['qc'];
            $qc['checks'] = $checks;
            $qc['rendered'] = in_array($qc['rendered'], [true, '1', 1], true);
            if ($qc['outcome'] !== 'Layak' && trim($qc['reason'] ?? '') === '') {
                throw new DomainException('reason_required');
            }
            $source = app(RadiographerStudySource::class)->inspect($w->id, $w->generation);
            if (! hash_equals($source['binding'], $input['binding'])) {
                throw new DomainException('source_changed');
            }
            $overlay = [];
            foreach (['studyDescription', 'bodyPart', 'projection', 'laterality', 'detectorType'] as $field) {
                $value = $input['overlay'][$field] ?? null;
                $overlay[$field] = ['value' => $value, 'authority' => $value === null || $value === '' ? 'unknown' : ($value === ($source['actual'][$field] ?? $source['source']['technical_fields'][$field]['value'] ?? null) ? 'radiographer-verified' : 'radiographer-corrected'),
                    'source_reference' => $source['binding'], 'source_revision' => 1, 'original_source' => $source['source']['technical_fields'][$field] ?? ['value' => $source['actual'][$field] ?? null, 'authority' => ! empty($source['actual'][$field]) ? 'embedded-observed' : 'unknown'], 'actor' => Auth::id(), 'verified_at' => app(Clock::class)->now()->format(DATE_ATOM), 'reason' => $input['overlay']['reason']];
            }
            $revision = $w->revision + 1;
            $qc += ['checklist_version' => 'B2-2026-10-14', 'actor' => Auth::id(), 'recorded_at' => app(Clock::class)->now()->format(DATE_ATOM)];
            DB::table('operator_radiographer_workspaces')->where('id', $w->id)->update(['revision' => $revision, 'binding' => $source['binding'], 'overlay' => json_encode($overlay, JSON_THROW_ON_ERROR), 'qc' => json_encode($qc, JSON_THROW_ON_ERROR)]);
            $fresh = DB::table('operator_radiographer_workspaces')->where('id', $w->id)->first();
            $this->history($fresh, 'qc-recorded', ['binding' => $source['binding'], 'overlay' => $overlay, 'qc' => $qc]);
            $this->audit($fresh, 'qc-recorded');

            return ['revision' => $revision];
        }, $generation);
    }

    public function handoff(string $workspace, #[\SensitiveParameter] array $input, int $generation): array
    {
        return $this->run($workspace, 'read', function ($w) use ($input) {
            $this->validate($input, ['revision' => 'required|integer|min:0', 'binding' => 'required|string|size:64', 'key' => 'required|string|max:64|regex:/^[a-zA-Z0-9_-]+$/']);
            $source = app(RadiographerStudySource::class)->inspect($w->id, $w->generation);
            if ($input['binding'] !== $source['binding'] || $w->binding !== $source['binding'] || (int) $input['revision'] !== $w->revision) {
                throw new DomainException('source_changed');
            }
            $digest = hash('sha256', json_encode([(int) $input['revision'], $input['binding']], JSON_THROW_ON_ERROR));
            $existing = DB::table('operator_radiographer_handoffs')->where('workspace_id', $w->id)->where('cycle', $w->cycle)->first();
            if ($existing) {
                if ($w->active_handoff !== $existing->id || $existing->actor_id !== Auth::id() || $existing->idempotency_key !== $input['key'] || $existing->payload_digest !== $digest) {
                    throw new DomainException('conflict');
                }

                return ['handoff' => $existing->id, 'state' => 'pending_radiologist_review'];
            }
            $overlay = json_decode($w->overlay ?? 'null', true, flags: JSON_THROW_ON_ERROR);
            $qc = json_decode($w->qc ?? 'null', true, flags: JSON_THROW_ON_ERROR);
            $p = $source['protocol'];
            if (! $overlay || ! $qc || $qc['outcome'] !== 'Layak' || ! $qc['rendered'] || in_array(false, $qc['checks'], true)
                || ! $p || ! $p['approved'] || ! $p['complete'] || $p['source_checksum'] !== $source['actual']['sha256']
                || ! $p['reference'] || ! $p['verified_by'] || ! $p['verified_at'] || new DateTimeImmutable($p['verified_at']) > app(Clock::class)->now()
                || ! in_array($p['projection'], ['PA', 'AP'], true) || $source['actual']['frames'] !== 1
                || $overlay['projection']['value'] !== $p['projection'] || $overlay['bodyPart']['value'] !== 'CHEST'
                || $overlay['studyDescription']['value'] !== $p['description'] || $overlay['laterality']['value'] !== $p['laterality']) {
                throw new DomainException('handoff_incomplete');
            }
            app(ClinicalReopenGuard::class)->initialize($w->booking_id);
            $id = (string) Str::uuid();
            $snapshot = ['binding' => $source['binding'], 'actual' => $source['actual'], 'protocol' => $p, 'overlay' => $overlay, 'qc' => $qc, 'generation' => $w->generation, 'revision' => $w->revision, 'site' => $w->site_id, 'case' => $w->booking_id];
            DB::table('operator_radiographer_handoffs')->insert(['id' => $id, 'workspace_id' => $w->id, 'cycle' => $w->cycle, 'actor_id' => Auth::id(), 'idempotency_key' => $input['key'], 'payload_digest' => $digest, 'binding' => $source['binding'], 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'occurred_at' => app(Clock::class)->now()]);
            DB::table('operator_radiographer_workspaces')->where('id', $w->id)->update(['active_handoff' => $id]);
            $this->history($w, 'handed-off', ['handoff' => $id, 'binding' => $source['binding']]);
            $this->audit($w, 'handed-off');

            return ['handoff' => $id, 'state' => 'pending_radiologist_review'];
        }, $generation);
    }

    private function validate(array $input, array $rules): void
    {
        if (array_diff(array_keys($input), array_keys($rules)) || Validator::make($input, $rules)->fails()) {
            throw new DomainException('invalid_input');
        }
        foreach ($input as $value) {
            if (is_string($value) && (str_contains($value, '<') || str_contains($value, '>') || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $value))) {
                throw new DomainException('invalid_input');
            }
        }
    }
}
