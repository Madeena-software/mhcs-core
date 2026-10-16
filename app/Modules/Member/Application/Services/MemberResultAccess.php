<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Models\User;
use App\Modules\Doctor\Application\Contracts\FinalReport;
use App\Modules\Member\Application\Contracts\ResultPublication;
use App\Modules\Operator\Application\Contracts\AdminSessionArrivals;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Authorization\AdminPanelAccessService;
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

final class MemberResultAccess implements ResultPublication
{
    private function transaction(callable $operation): mixed
    {
        if (DB::transactionLevel() !== 0) {
            throw new DomainException('result_unavailable');
        }
        try {
            return DB::transaction($operation);
        } catch (Throwable) {
            if (DB::transactionLevel() === 0 && DB::connection()->getPdo()->inTransaction()) {
                DB::connection()->getPdo()->rollBack();
            }
            $this->denied();
            throw new DomainException('result_unavailable');
        }
    }

    public function denied(): void
    {
        $this->audit(null, 'denied', 'rejected');
    }

    private function audit(?object $p, string $action, string $outcome = 'success'): void
    {
        $c = app(AuthenticatedContextProvider::class)->current();
        $context = new AuthenticatedContext(actorId: $c->actorId, operationId: $c->operationId, sessionId: $c->sessionId,
            roles: $c->roles, permissions: $c->permissions, siteId: $p ? LocalId::fromString($p->site_id) : null,
            caseId: $p ? LocalId::fromString($p->case_id) : null, purpose: 'member.result');
        app(AuditStore::class)->append(AuditEvent::fromContext($context, 'member.result.'.$action, 'member', $outcome,
            app(Clock::class)->now(), targetType: 'result-publication', targetId: $p?->id,
            metadata: $p ? ['generation' => (int) $p->generation] : []));
    }

    private function account(string $id): User
    {
        $u = User::query()->whereKey($id)->lockForUpdate()->first();
        if (! $u || ! $u->canAuthenticate()) {
            throw new DomainException('result_unavailable');
        }

        return $u;
    }

    private function manager(string $action, ?string $site = null, bool $lock = true): void
    {
        $u = $lock ? $this->account((string) Auth::id()) : User::query()->find(Auth::id());
        if (! $u || ! $u->canAuthenticate()) {
            throw new DomainException('result_unavailable');
        }
        $roles = DB::table('authorization_role_assignments')->where('user_id', $u->id)->when($lock, fn ($q) => $q->lockForUpdate())->get();
        $permissions = DB::table('authorization_permission_assignments')->where('user_id', $u->id)->when($lock, fn ($q) => $q->lockForUpdate())->get();
        $c = app(MemberAuthorization::class)->context('member.result-publication');
        if ((string) $c->actorId !== $u->id
            || ! array_intersect($permissions->where('active', true)->pluck('permission')->all(), (array) config('mhcs.admin_panel.access_permissions', []))
            || ! $roles->contains(fn ($r) => $r->active && $r->role === 'administrator')
            || ! array_intersect($permissions->where('active', true)->pluck('permission')->all(), array_map(fn ($a) => 'member.result-publication.'.$a, $action === 'status' ? ['publish', 'hold', 'suspend', 'revoke'] : [$action === 'read' ? 'publish' : $action]))
            || ! app(AdminPanelAccessService::class)->canAccess($u, 'admin')
            || ($site !== null && ! DB::table('member_result_managers')->where('user_id', $u->id)->where('site_id', $site)->where('active', true)->lockForUpdate()->exists())) {
            throw new DomainException('result_unavailable');
        }
    }

    private function participants(string $case): array
    {
        $b = DB::table('bookings')->where('id', $case)->first();
        $m = $b ? DB::table('members')->where('id', $b->member_id)->first() : null;
        $locator = app(FinalReport::class)->locate($case);
        if (! $b || ! $m) {
            throw new DomainException('result_unavailable');
        }
        User::query()->whereIn('id', array_unique([(string) Auth::id(), $m->user_id, $locator['clinician']]))->orderBy('id')->lockForUpdate()->get();

        return [$b, $m, $locator];
    }

    private function recipient(string $case, array $report, string $expectedUser): array
    {
        $b = DB::table('bookings')->where('id', $case)->lockForUpdate()->first();
        $s = $b ? DB::table('shift_schedules')->where('id', $b->shift_schedule_id)->lockForUpdate()->first() : null;
        $f = $b ? DB::table('examination_site_refs')->where('id', $b->examination_site_id_snapshot)->lockForUpdate()->first() : null;
        $org = $f ? DB::table('operator_organization_refs')->where('id', $f->operator_organization_ref_id)->lockForUpdate()->first() : null;
        $m = $b ? DB::table('members')->where('id', $b->member_id)->lockForUpdate()->first() : null;
        if (! $b || ! $s || ! $f || ! $org || ! $m || $m->user_id !== $expectedUser || $m->identity_status !== 'verified'
            || $m->registration_source === 'nonclinical_validation' || $m->identity_document_type !== 'ktp'
            || DB::table('members')->where('user_id', $expectedUser)->count() !== 1
            || ! $m->encrypted_nik || ! $m->nik_lookup_digest
            || ! $f->active || ! $org->active || $s->examination_site_id !== $f->id
            || $b->shift_schedule_id !== $report['schedule'] || $b->service_offering_id !== $report['service'] || $s->service_offering_id !== $report['service']) {
            throw new DomainException('result_unavailable');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr((string) $m->birth_date, 0, 10));
        $today = app(Clock::class)->now()->setTime(0, 0);
        if (! $date || $date->format('Y-m-d') !== $m->birth_date || $date > $today->modify('-18 years')) {
            throw new DomainException('result_unavailable');
        }
        $subject = ['patientId' => $m->medical_record_number, 'patientName' => $m->name, 'birthDate' => str_replace('-', '', $m->birth_date),
            'sex' => match (strtolower($m->administrative_gender)) {
                'female', 'f' => 'F', 'male', 'm' => 'M', 'other' => 'O', default => null
            }];
        if ($subject != $report['subject']) {
            throw new DomainException('result_unavailable');
        }
        $mapped = app(AdminSessionArrivals::class)->mapping(['stable' => $f->operator_site_id, 'organization' => $org->operator_organization_id, 'timezone' => $f->timezone, 'version' => $f->source_version, 'active' => $f->active]);
        if ($mapped !== $report['site']) {
            throw new DomainException('result_unavailable');
        }
        $assets = DB::table('member_verification_assets')->where('member_id', $m->id)->where('is_current', true)->orderBy('id')->lockForUpdate()->get();
        foreach (['ktp', 'profile_photo'] as $type) {
            $slot = $assets->where('type', $type);
            $a = $slot->first();
            if ($slot->count() !== 1 || ! $a || $a->review_status !== 'approved' || ! $a->reviewed_by_user_id || ! $a->reviewed_at
                || new DateTimeImmutable($a->reviewed_at) > app(Clock::class)->now() || $a->bytes < 1 || ! $a->private_object_key || ! preg_match('/\A[a-f0-9]{64}\z/', $a->checksum)) {
                throw new DomainException('result_unavailable');
            }
        }
        if ($assets->where('type', 'kia')->isNotEmpty()) {
            throw new DomainException('result_unavailable');
        }
        $this->account($expectedUser);

        return ['member' => $m, 'identity_digest' => hash('sha256', json_encode([$m->id, $m->user_id, $m->birth_date, $m->nik_lookup_digest, $assets->all()], JSON_THROW_ON_ERROR)),
            'metadata' => ['examination' => $b->service_code_snapshot, 'facility' => $f->display_name, 'date' => $s->starts_at]];
    }

    private function head(string $case): object
    {
        DB::table('member_result_cases')->insertOrIgnore(['case_id' => $case, 'revision' => 0]);

        return DB::table('member_result_cases')->where('case_id', $case)->lockForUpdate()->first();
    }

    public function manage(string $case, string $action, array $input = []): array
    {
        return $this->transaction(function () use ($case, $action, $input) {
            if (! in_array($action, ['read', 'status', 'publish', 'hold', 'suspend', 'revoke'], true)) {
                throw new DomainException('result_unavailable');
            }
            // Reject broad panel/staff authority before even discovering clinical references.
            $this->manager($action, lock: false);
            if (in_array($action, ['status', 'hold', 'suspend', 'revoke'], true)) {
                $existing = DB::table('member_result_cases as c')->join('member_result_publications as p', 'p.id', '=', 'c.publication_id')->where('c.case_id', $case)->select('p.*')->first();
                if (! $existing) {
                    throw new DomainException('result_unavailable');
                }
                User::query()->whereIn('id', array_unique([(string) Auth::id(), $existing->user_id, $existing->clinician_id]))->orderBy('id')->lockForUpdate()->get();
                $locator = ['site' => $existing->site_id];
            } else {
                [$b, $m, $locator] = $this->participants($case);
            }
            $this->manager($action, $locator['site']);
            $report = null;
            if (in_array($action, ['read', 'publish'], true)) {
                $selected = $input['report'] ?? $locator['report'];
                if (! is_string($selected)) {
                    throw new DomainException('result_unavailable');
                }
                $report = app(FinalReport::class)->readWhileLocked($case, $selected);
                if ($report['site'] !== $locator['site'] || $locator !== app(FinalReport::class)->locate($case)) {
                    throw new DomainException('result_unavailable');
                }
                $recipient = $this->recipient($case, $report, $m->user_id);
            } else {
                // Withdrawal remains possible when identity or clinical evidence becomes invalid.
                DB::table('bookings')->where('id', $case)->lockForUpdate()->first();
            }
            $head = $this->head($case);
            $p = $head->publication_id ? DB::table('member_result_publications')->where('id', $head->publication_id)->lockForUpdate()->first() : null;
            if ($action === 'status') {
                if (! $p || $p->id !== $existing->id || $p->site_id !== $locator['site']) {
                    throw new DomainException('result_unavailable');
                }
                $this->audit($p, 'status');

                return ['revision' => $head->revision, 'publication' => (object) ['id' => $p->id, 'state' => $p->state, 'expires_at' => $p->expires_at], 'report' => null, 'member' => null, 'recipient' => null];
            }
            if ($action === 'read') {
                $this->audit($p, 'preview');

                return ['revision' => $head->revision, 'publication' => $p ? (object) ['id' => $p->id, 'state' => $p->state, 'expires_at' => $p->expires_at] : null, 'report' => $report, 'member' => $recipient['member']->id, 'recipient' => $recipient['member']->name];
            }
            $rules = ['revision' => 'required|integer|min:0', 'key' => 'required|string|max:64|regex:/\A[a-zA-Z0-9_-]+\z/'];
            if ($action === 'publish') {
                $rules += ['report' => 'required|string|uuid', 'member' => 'required|string|uuid', 'confirm' => 'required|accepted'];
            }
            if (array_diff(array_keys($input), array_keys($rules)) || Validator::make($input, $rules)->fails()) {
                throw new DomainException('result_unavailable');
            }
            $payload = $input;
            ksort($payload);
            $digest = hash('sha256', json_encode([$action, $payload], JSON_THROW_ON_ERROR));
            $prior = DB::table('member_result_history')->where('case_id', $case)->where('operation_key', $input['key'])->first();
            if ($prior) {
                if ($prior->payload_digest !== $digest || $prior->actor_id !== Auth::id() || $prior->publication_id !== $head->publication_id || $prior->revision !== $head->revision || ($action === 'publish' && ($p->state !== 'published' || new DateTimeImmutable($p->expires_at) <= app(Clock::class)->now()))) {
                    throw new DomainException('result_unavailable');
                }
                $this->audit($p, 'retry');

                return $this->decision($p, $head->revision, $action === 'publish');
            }
            if ((int) $input['revision'] !== $head->revision) {
                throw new DomainException('result_unavailable');
            }
            $revision = $head->revision + 1;
            $now = app(Clock::class)->now();
            if ($action === 'publish') {
                if ($input['member'] !== $recipient['member']->id || ($p && $p->state === 'published' && new DateTimeImmutable($p->expires_at) > $now)) {
                    throw new DomainException('result_unavailable');
                }
                $data = ['id' => (string) Str::uuid(), 'case_id' => $case, 'generation' => ($p?->generation ?? 0) + 1,
                    'member_id' => $recipient['member']->id, 'user_id' => $m->user_id, 'clinician_id' => $locator['clinician'], 'site_id' => $report['site'], 'report_id' => $report['report'],
                    'finalization_id' => $report['finalization'], 'handoff_id' => $report['handoff'], 'publisher_id' => Auth::id(),
                    'digest' => $report['digest'], 'binding' => $report['binding'], 'identity_digest' => $recipient['identity_digest'], 'state' => 'published',
                    'published_at' => $now, 'expires_at' => $now->modify('+24 hours')];
                DB::table('member_result_publications')->insert($data);
                $p = DB::table('member_result_publications')->where('id', $data['id'])->first();
            } else {
                if (! $p || $p->state === 'revoked' || $p->site_id !== $locator['site']) {
                    throw new DomainException('result_unavailable');
                }
                DB::table('member_result_publications')->where('id', $p->id)->update(['state' => match ($action) {
                    'hold' => 'held', 'suspend' => 'suspended', 'revoke' => 'revoked'
                }]);
            }
            DB::table('member_result_cases')->where('case_id', $case)->update(['revision' => $revision, 'publication_id' => $p->id]);
            DB::table('member_result_history')->insert(['id' => (string) Str::uuid(), 'case_id' => $case, 'publication_id' => $p->id, 'revision' => $revision,
                'actor_id' => Auth::id(), 'action' => $action, 'operation_key' => $input['key'], 'payload_digest' => $digest, 'occurred_at' => $now]);
            $this->audit($p, $action);

            return $this->decision($p, $revision, $action === 'publish');
        });
    }

    private function decision(object $p, int $revision, bool $locator): array
    {
        return ['revision' => $revision, 'id' => $p->id, 'url' => $locator ? URL::temporarySignedRoute('member.result.entry', new DateTimeImmutable($p->expires_at), ['publication' => $p->id, 'generation' => $p->generation]) : null];
    }

    public function read(string $publication, int $generation, bool $entry = false): array
    {
        return $this->transaction(function () use ($publication, $generation, $entry) {
            $actor = (string) Auth::id();
            $p = DB::table('member_result_publications')->where('id', $publication)->first();
            if (! $p || $p->user_id !== $actor) {
                throw new DomainException('result_unavailable');
            }
            [, $m, $locator] = $this->participants($p->case_id);
            $u = $this->account($actor);
            $session = request()->hasSession() ? request()->session() : null;
            $credential = $session?->get('member.result.credential');
            if (! $session || ! is_string($credential) || ! hash_equals($credential, hash('sha256', $u->getAuthPassword()))
                || $m->id !== $p->member_id || $m->user_id !== $actor || $locator['site'] !== $p->site_id) {
                throw new DomainException('result_unavailable');
            }
            $report = app(FinalReport::class)->readWhileLocked($p->case_id, $p->report_id);
            $recipient = $this->recipient($p->case_id, $report, $actor);
            $head = DB::table('member_result_cases')->where('case_id', $p->case_id)->lockForUpdate()->first();
            $p = DB::table('member_result_publications')->where('id', $publication)->lockForUpdate()->first();
            if (! $head || $head->publication_id !== $p->id || $p->generation !== $generation || $p->state !== 'published'
                || new DateTimeImmutable($p->expires_at) <= app(Clock::class)->now() || new DateTimeImmutable($p->published_at) > app(Clock::class)->now()
                || $p->identity_digest !== $recipient['identity_digest'] || $p->finalization_id !== $report['finalization'] || $p->digest !== $report['digest']
                || $p->binding !== $report['binding'] || $p->handoff_id !== $report['handoff'] || $locator !== app(FinalReport::class)->locate($p->case_id)) {
                throw new DomainException('result_unavailable');
            }
            $this->audit($p, $entry ? 'entry' : 'access');
            if (new DateTimeImmutable($p->expires_at) <= app(Clock::class)->now()) {
                throw new DomainException('result_unavailable');
            }

            return array_intersect_key($report, array_flip(['findings', 'conclusion', 'recommendations', 'clinician', 'finalized_at'])) + ['metadata' => $recipient['metadata']];
        });
    }
}
