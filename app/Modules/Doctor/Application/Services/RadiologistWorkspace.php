<?php

declare(strict_types=1);

namespace App\Modules\Doctor\Application\Services;

use App\Models\User;
use App\Modules\Doctor\Application\Contracts\RadiologistAccess;
use App\Modules\ImageGateway\Application\Contracts\RadiologistStudySource;
use App\Modules\Member\Application\Contracts\RadiologistCaseIdentity;
use App\Modules\Operator\Application\Contracts\PendingRadiographerHandoff;
use App\Modules\Operator\Application\Contracts\RadiologistHandoff;
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
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

final class RadiologistWorkspace implements RadiologistAccess
{
    public const REASONS = ['positioning', 'coverage', 'exposure', 'artifacts', 'identity_concern', 'other'];

    private int $depth = 0;

    private bool $entering = false;

    private function user(): User
    {
        $u = Auth::id() ? User::query()->whereKey(Auth::id())->lockForUpdate()->first() : null;
        if (! $u || ! $u->canAuthenticate()) {
            throw new DomainException('access_denied');
        }

        return $u;
    }

    public function managementAvailable(): bool
    {
        $u = Auth::user();

        return $u instanceof User && $u->canAuthenticate() && app(AdminPanelAccessService::class)->canAccess($u, 'admin')
            && array_intersect(app(AuthorizationClaimResolver::class)->permissions($u), array_map(fn ($a) => 'doctor.radiologist-workspace.'.$a, ['issue', 'revoke', 'reassign', 'credential'])) !== [];
    }

    public function managementHandoff(string $reference): string
    {
        return $this->transaction(function () use ($reference) {
            $this->authorizeManager();
            $handoff = app(RadiologistStudySource::class)->handoffForStudy($reference);
            if (! $handoff) {
                throw new DomainException('unavailable');
            }
            $h = app(RadiologistHandoff::class)->selection($handoff);
            $this->audit((object) ['id' => $handoff, 'booking_id' => $h->booking_id, 'site_id' => $h->site_id, 'generation' => 0, 'revision' => 0], 'management-locator');

            return $handoff;
        });
    }

    public function authorizeManager(?string $site = null): void
    {
        $u = $this->user();
        DB::table('authorization_role_assignments')->where('user_id', $u->id)->lockForUpdate()->get();
        DB::table('authorization_permission_assignments')->where('user_id', $u->id)->lockForUpdate()->get();
        $permissions = app(AuthorizationClaimResolver::class)->permissions($u);
        if (! app(AdminPanelAccessService::class)->canAccess($u, 'admin')
            || ! array_intersect($permissions, array_map(fn ($a) => 'doctor.radiologist-workspace.'.$a, ['issue', 'revoke', 'reassign', 'credential']))) {
            throw new DomainException('access_denied');
        }
        if ($site !== null && ! DB::table('doctor_workspace_managers')->where('user_id', $u->id)->where('site_id', $site)->where('active', true)->lockForUpdate()->exists()) {
            throw new DomainException('access_denied');
        }
    }

    private function capability(string $action, ?string $site = null): void
    {
        $this->authorizeManager($site);
        if (! in_array('doctor.radiologist-workspace.'.$action, app(AuthorizationClaimResolver::class)->permissions($this->user()), true)) {
            throw new DomainException('access_denied');
        }
    }

    private function credential(#[\SensitiveParameter] string $password): string
    {
        $u = $this->user();
        $request = request();
        if (! $request->hasSession() || ! $request->session()->isStarted()) {
            throw new DomainException('access_denied');
        }
        $session = $request->session()->getId();
        $key = 'doctor-credential:'.hash('sha256', $u->id);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new DomainException('access_denied');
        }
        RateLimiter::hit($key, 60);
        if (! Auth::guard('web')->getProvider()->validateCredentials($u, ['password' => $password])) {
            throw new DomainException('access_denied');
        }

        RateLimiter::clear($key);

        return hash('sha256', $session);
    }

    private function eligible(string $user, string $site, string $service, string $modality): object
    {
        $u = User::query()->whereKey($user)->lockForUpdate()->first();
        $e = DB::table('doctor_eligibility')->where('user_id', $user)->where('site_id', $site)->where('service_id', $service)->where('modality', $modality)->orderByDesc('revision')->lockForUpdate()->first();
        $now = app(Clock::class)->now();
        if (! in_array($modality, ['DX', 'CR'], true) || ! $u || ! $u->canAuthenticate() || ! $e || $e->status !== 'verified' || ! $e->evidence_reference || ! $e->verifier_id
            || new DateTimeImmutable($e->verified_at) > $now || new DateTimeImmutable($e->valid_until) <= $now) {
            throw new DomainException('access_denied');
        }

        return $e;
    }

    public function verifyEligibility(string $handoff, #[\SensitiveParameter] array $input): array
    {
        return $this->transaction(function () use ($handoff, $input) {
            $this->capability('credential');
            $h = app(RadiologistHandoff::class)->selection($handoff);
            $this->capability('credential', $h->site_id);
            $this->validate($input, ['user' => 'required|string|max:36', 'status' => 'required|in:verified,revoked', 'evidence' => 'required|string|max:255', 'valid_until' => 'required|date', 'password' => 'required|string|max:4096']);
            $this->credential($input['password']);
            $service = app(RadiologistCaseIdentity::class)->service($h->booking_id, $h->member_schedule_id, $h->site_id);
            $modality = app(RadiologistStudySource::class)->assignment($handoff)['modality'];
            if (! in_array($modality, ['DX', 'CR'], true) || new DateTimeImmutable($input['valid_until']) <= app(Clock::class)->now()) {
                throw new DomainException('invalid_input');
            }
            $u = User::query()->whereKey($input['user'])->lockForUpdate()->first();
            if (! $u || ! $u->canAuthenticate()) {
                throw new DomainException('access_denied');
            }
            $old = DB::table('doctor_eligibility')->where('user_id', $u->id)->where('site_id', $h->site_id)->where('service_id', $service)->where('modality', $modality)->orderByDesc('revision')->lockForUpdate()->first();
            $id = (string) Str::uuid();
            DB::table('doctor_eligibility')->insert(['id' => $id, 'user_id' => $u->id, 'site_id' => $h->site_id, 'service_id' => $service, 'modality' => $modality, 'revision' => ($old?->revision ?? 0) + 1, 'status' => $input['status'], 'evidence_reference' => $input['evidence'], 'verifier_id' => Auth::id(), 'verified_at' => app(Clock::class)->now(), 'valid_until' => new DateTimeImmutable($input['valid_until'])]);
            $this->audit((object) ['id' => $id, 'site_id' => $h->site_id, 'booking_id' => $h->booking_id, 'generation' => 0, 'revision' => ($old?->revision ?? 0) + 1], 'credential-'.$input['status']);

            return ['eligibility' => $id];
        });
    }

    private function authorized(string $workspace, int $generation): object
    {
        $u = $this->user();
        // Discover the immutable handoff locator before taking the upstream lock.
        $row = DB::table('doctor_workspaces')->where('id', $workspace)->first();
        if (! $row || ! app(PendingRadiographerHandoff::class)->isCurrent($row->handoff_id, $row->binding)) {
            throw new DomainException('access_denied');
        }
        app(ClinicalFinalizationGuard::class)->initialize($row->booking_id);
        $w = DB::table('doctor_workspaces')->where('id', $workspace)->lockForUpdate()->first();
        $now = app(Clock::class)->now();
        if ($w->generation !== $generation || $w->user_id !== $u->id || $w->revoked || new DateTimeImmutable($w->starts_at) > $now || new DateTimeImmutable($w->expires_at) <= $now) {
            throw new DomainException('access_denied');
        }
        $e = $this->eligible($u->id, $w->site_id, $w->service_id, $w->modality);
        if ($w->eligibility_id !== $e->id) {
            throw new DomainException('access_denied');
        }
        if (! $this->entering) {
            $session = request()->hasSession() ? request()->session()->get('radiologist.bindings.'.$workspace) : null;
            if (! is_array($session) || ($session['generation'] ?? null) !== $generation || ! hash_equals($session['credential'] ?? '', hash('sha256', $u->getAuthPassword()))) {
                throw new DomainException('access_denied');
            }
        }
        $w->protocol = (object) json_decode($w->snapshot, true, flags: JSON_THROW_ON_ERROR)['protocol'];

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
                $w = $this->authorized($workspace, $generation);
                $value = $operation($w);
                if ($this->depth === 1) {
                    app(RadiologistStudySource::class)->inspectClinical($workspace, $generation);
                }
                $this->authorized($workspace, $generation);
                $this->audit($w, $action);
                if ($this->depth === 1) {
                    app(RadiologistStudySource::class)->inspectClinical($workspace, $generation);
                }
                $this->authorized($workspace, $generation);

                return $value;
            });
        } catch (Throwable) {
            $this->rollback();
            $this->denied();
            throw new DomainException('workspace_unavailable');
        } finally {
            $this->depth--;
        }
    }

    private function transaction(callable $operation): mixed
    {
        if (DB::transactionLevel() !== 0) {
            throw new DomainException('unavailable');
        }
        try {
            return DB::transaction($operation);
        } catch (Throwable) {
            $this->rollback();
            $this->denied();
            throw new DomainException('workspace_unavailable');
        }
    }

    private function rollback(): void
    {
        if (DB::transactionLevel() === 0 && DB::connection()->getPdo()->inTransaction()) {
            DB::connection()->getPdo()->rollBack();
        }
    }

    public function denied(): void
    {
        $this->rollback();
        $this->audit(null, 'denied', 'rejected');
    }

    private function audit(?object $w, string $action, string $outcome = 'success'): void
    {
        $c = app(AuthenticatedContextProvider::class)->current();
        $context = new AuthenticatedContext(actorId: $c->actorId, operationId: $c->operationId, sessionId: $c->sessionId, roles: $c->roles, permissions: $c->permissions,
            siteId: $w ? LocalId::fromString($w->site_id) : null, caseId: $w ? LocalId::fromString($w->booking_id) : null, purpose: 'radiologist.workspace');
        app(AuditStore::class)->append(AuditEvent::fromContext($context, 'radiologist-workspace.'.$action, 'doctor', $outcome, app(Clock::class)->now(), targetType: 'radiologist-workspace', targetId: $w?->id, metadata: $w ? ['generation' => (int) $w->generation, 'revision' => (int) $w->revision] : []));
    }

    private function history(object $w, string $action, array $data): void
    {
        DB::table('doctor_workspace_history')->insert(['id' => (string) Str::uuid(), 'workspace_id' => $w->id, 'actor_id' => Auth::id(), 'action' => $action, 'generation' => $w->generation, 'revision' => $w->revision, 'data' => json_encode($data, JSON_THROW_ON_ERROR), 'occurred_at' => app(Clock::class)->now()]);
    }

    public function manage(string $handoff, string $action, #[\SensitiveParameter] array $input): array
    {
        return $this->transaction(function () use ($handoff, $action, $input) {
            $this->authorizeManager();
            $h = app(RadiologistHandoff::class)->selection($handoff);
            app(ClinicalFinalizationGuard::class)->initialize($h->booking_id);
            $w = DB::table('doctor_workspaces')->where('handoff_id', $handoff)->lockForUpdate()->first();
            $service = app(RadiologistCaseIdentity::class)->service($h->booking_id, $h->member_schedule_id, $h->site_id);
            $modality = $w?->modality ?? ($h->snapshot['actual']['modality'] ?? null);
            if (in_array($action, ['issue', 'reassign'], true) || ($action === 'read' && $modality === null)) {
                $modality = app(RadiologistStudySource::class)->assignment($handoff)['modality'];
            }
            if ($action === 'read') {
                $this->audit($w ?? (object) ['id' => $h->handoff_id, 'site_id' => $h->site_id, 'booking_id' => $h->booking_id, 'generation' => 0, 'revision' => 0], 'management-read');
                $candidates = [];
                foreach (DB::table('doctor_eligibility')->where('site_id', $h->site_id)->where('service_id', $service)->where('modality', $modality)->distinct()->limit(100)->pluck('user_id') as $id) {
                    try {
                        $this->eligible($id, $h->site_id, $service, $modality);
                        $candidates[$id] = User::query()->whereKey($id)->value('email');
                    } catch (DomainException) {
                    }
                }

                return ['id' => $w?->id, 'generation' => $w?->generation, 'revision' => $w?->revision, 'revoked' => (bool) $w?->revoked, 'finalized' => $w?->final_id !== null, 'candidates' => $candidates];
            }
            if (! in_array($action, ['issue', 'reassign', 'revoke'], true)) {
                throw new DomainException('invalid_action');
            }
            $this->capability($action, $h->site_id);
            $rules = ['password' => 'required|string|max:4096'];
            if ($action !== 'revoke') {
                $input += ['hours' => 24];
                $rules += ['user' => 'required|string|max:36', 'hours' => 'required|integer|min:1|max:72'];
            }
            if ($action !== 'issue') {
                $rules += ['generation' => 'required|integer|min:1'];
            }
            $this->validate($input, $rules);
            $this->credential($input['password']);
            if (($action === 'issue' && $w) || ($action !== 'issue' && (! $w || (int) $input['generation'] !== $w->generation)) || ($action === 'reassign' && $w->final_id)) {
                throw new DomainException('conflict');
            }
            if ($action === 'revoke') {
                DB::table('doctor_workspaces')->where('id', $w->id)->update(['revoked' => true, 'generation' => $w->generation + 1]);
            } else {
                $e = $this->eligible($input['user'], $h->site_id, $service, $modality);
                $now = app(Clock::class)->now();
                $values = ['user_id' => $input['user'], 'eligibility_id' => $e->id, 'generation' => $w ? $w->generation + 1 : 1, 'starts_at' => $now, 'expires_at' => $now->modify('+'.$input['hours'].' hours'), 'revoked' => false];
                if ($w) {
                    DB::table('doctor_workspaces')->where('id', $w->id)->update($values + ['draft_id' => null, 'quality' => null, 'revision' => $w->revision + 1]);
                } else {
                    $id = (string) Str::uuid();
                    DB::table('doctor_workspaces')->insert($values + ['id' => $id, 'study_id' => $h->study_id, 'booking_id' => $h->booking_id, 'admission_id' => $h->admission_id, 'site_id' => $h->site_id, 'member_schedule_id' => $h->member_schedule_id, 'service_id' => $service, 'modality' => $modality, 'binding' => $h->binding, 'handoff_id' => $handoff, 'clinical_status' => 'not_finalized', 'snapshot' => json_encode($h->snapshot, JSON_THROW_ON_ERROR)]);
                    $w = (object) ['id' => $id];
                }
            }
            $w = DB::table('doctor_workspaces')->where('id', $w->id)->first();
            $this->history($w, $action, ['assignee' => $w->user_id]);
            $this->audit($w, $action);
            $result = ['id' => $w->id, 'generation' => $w->generation];
            if ($action !== 'revoke') {
                $result['url'] = URL::temporarySignedRoute('radiologist.entry', new DateTimeImmutable($w->expires_at), ['workspace' => $w->id, 'generation' => $w->generation]);
            }

            return $result;
        });
    }

    public function entry(string $workspace, int $generation): void
    {
        $this->entering = true;
        try {
            $this->run($workspace, 'entry', fn ($w) => app(RadiologistStudySource::class)->inspectClinical($w->id, $generation), $generation);
            request()->session()->put('radiologist.bindings.'.$workspace, ['generation' => $generation, 'credential' => hash('sha256', $this->user()->getAuthPassword())]);
        } finally {
            $this->entering = false;
        }
    }

    public function read(string $workspace, int $generation): array
    {
        return $this->run($workspace, 'read', function ($w) use ($generation) {
            $s = app(RadiologistStudySource::class)->inspectClinical($w->id, $generation);
            $report = $w->draft_id ? DB::table('doctor_report_versions')->where('id', $w->draft_id)->first() : null;
            $history = DB::table('doctor_report_versions')->where('workspace_id', $w->id)->where('generation', $generation)->orderBy('revision')->get(['revision', 'author_id', 'findings', 'conclusion', 'recommendations', 'created_at'])->all();

            return ['identity' => $s['identity'], 'actual' => $s['actual'], 'display_reference' => $s['display_reference'], 'binding' => $w->binding, 'handoff' => $w->handoff_id, 'revision' => $w->revision, 'generation' => $w->generation, 'quality' => $w->quality, 'read_only' => $w->final_id !== null, 'report' => $report, 'finalization' => $w->final_id ? DB::table('doctor_finalizations')->where('id', $w->final_id)->first(['author_id', 'finalizer_id', 'finalized_at', 'attestation']) : null, 'history' => $history, 'expires_at' => $w->expires_at];
        }, $generation);
    }

    public function dicom(string $workspace, int $generation): string
    {
        return app(RadiologistStudySource::class)->inspectClinical($workspace, $generation)['bytes'];
    }

    public function command(string $workspace, string $action, #[\SensitiveParameter] array $input, int $generation): array
    {
        return $this->run($workspace, $action, function ($w) use ($input, $action, $generation) {
            $rules = ['revision' => 'required|integer|min:0', 'binding' => 'required|string|size:64', 'handoff' => 'required|string|max:36'];
            $rules += match ($action) {
                'quality' => ['decision' => 'required|in:usable,repeat_required', 'reason' => 'nullable|in:'.implode(',', self::REASONS), 'explanation' => 'nullable|string|max:1000'],
                'draft' => ['findings' => 'present|nullable|string|max:20000', 'conclusion' => 'present|nullable|string|max:20000', 'recommendations' => 'present|nullable|string|max:20000'],
                'finalize' => ['password' => 'required|string|max:4096', 'attest' => 'required|accepted', 'key' => 'required|string|max:64|regex:/^[a-zA-Z0-9_-]+$/'],
                default => throw new DomainException('invalid_action'),
            };
            $this->validate($input, $rules);
            if ($input['binding'] !== $w->binding || $input['handoff'] !== $w->handoff_id || (int) $input['revision'] !== $w->revision) {
                throw new DomainException('conflict');
            }
            app(RadiologistStudySource::class)->inspectClinical($w->id, $generation);
            if ($w->final_id) {
                if ($action !== 'finalize') {
                    throw new DomainException('immutable');
                }
                $this->credential($input['password']);
                $f = DB::table('doctor_finalizations')->where('id', $w->final_id)->first();
                if ($f->operation_key !== $input['key'] || $f->finalizer_id !== Auth::id()) {
                    throw new DomainException('conflict');
                }

                return ['revision' => $w->revision, 'state' => 'finalized'];
            }
            $revision = $w->revision + 1;
            if ($action === 'quality') {
                if ($input['decision'] === 'repeat_required' && (empty($input['reason']) || ($input['reason'] === 'other' && trim($input['explanation'] ?? '') === ''))) {
                    throw new DomainException('reason_required');
                }
                DB::table('doctor_workspaces')->where('id', $w->id)->update(['quality' => $input['decision'], 'revision' => $revision]);
                $data = ['decision' => $input['decision'], 'reason' => $input['reason'] ?? null, 'explanation' => $input['explanation'] ?? null, 'binding' => $w->binding, 'handoff' => $w->handoff_id];
                $state = $input['decision'] === 'repeat_required' ? 'repeat_pending' : 'usable';
            } elseif ($action === 'draft') {
                $id = (string) Str::uuid();
                $content = array_map(fn ($k) => (string) ($input[$k] ?? ''), ['findings', 'conclusion', 'recommendations']);
                DB::table('doctor_report_versions')->insert(['id' => $id, 'workspace_id' => $w->id, 'generation' => $generation, 'revision' => $revision, 'author_id' => Auth::id(), 'binding' => $w->binding, 'handoff_id' => $w->handoff_id, 'findings' => $content[0], 'conclusion' => $content[1], 'recommendations' => $content[2], 'digest' => hash('sha256', json_encode($content, JSON_THROW_ON_ERROR)), 'created_at' => app(Clock::class)->now()]);
                DB::table('doctor_workspaces')->where('id', $w->id)->update(['draft_id' => $id, 'revision' => $revision]);
                $data = ['report' => $id];
                $state = 'draft';
            } else {
                $session = $this->credential($input['password']);
                $report = $w->draft_id ? DB::table('doctor_report_versions')->where('id', $w->draft_id)->first() : null;
                if ($w->quality !== 'usable' || ! $report || $report->author_id !== Auth::id() || $report->generation !== $generation || $report->binding !== $w->binding || $report->handoff_id !== $w->handoff_id
                    || ! $this->meaningful($report->findings) || ! $this->meaningful($report->conclusion)
                    || DB::table('doctor_finalizations')->where('case_id', $w->booking_id)->exists()) {
                    throw new DomainException('incomplete');
                }
                $id = (string) Str::uuid();
                DB::table('doctor_finalizations')->insert(['id' => $id, 'workspace_id' => $w->id, 'case_id' => $w->booking_id, 'report_id' => $report->id, 'revision' => $w->revision, 'author_id' => $report->author_id, 'finalizer_id' => Auth::id(), 'binding' => $w->binding, 'handoff_id' => $w->handoff_id, 'digest' => $report->digest, 'operation_key' => $input['key'], 'session_digest' => $session, 'attestation' => 'I explicitly attest to my authored findings and conclusion. Internal clinical attestation; not a certified electronic signature.', 'finalized_at' => app(Clock::class)->now()]);
                DB::table('doctor_workspaces')->where('id', $w->id)->update(['final_id' => $id, 'clinical_status' => 'finalized']);
                $revision = $w->revision;
                $data = ['finalization' => $id, 'report' => $report->id];
                $state = 'finalized';
            }
            $fresh = DB::table('doctor_workspaces')->where('id', $w->id)->first();
            $this->history($fresh, $action, $data);
            $this->audit($fresh, $action.'-committed');
            // The same case lock covers the final report, clinical status, history and required audit.
            DB::table('doctor_clinical_guards')->where('case_id', $w->booking_id)->update(['status' => $state === 'finalized' ? 'finalized' : 'not_finalized']);

            return ['revision' => $revision, 'state' => $state];
        }, $generation);
    }

    private function meaningful(string $text): bool
    {
        return preg_match('/[\p{L}\p{N}]/u', strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) === 1;
    }

    private function validate(#[\SensitiveParameter] array $input, array $rules): void
    {
        if (array_diff(array_keys($input), array_keys($rules)) || Validator::make($input, $rules)->fails()) {
            throw new DomainException('invalid_input');
        }
        foreach ($input as $key => $value) {
            if ($key !== 'password' && is_string($value) && (str_contains($value, '<') || str_contains($value, '>') || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $value))) {
                throw new DomainException('invalid_input');
            }
        }
    }
}
