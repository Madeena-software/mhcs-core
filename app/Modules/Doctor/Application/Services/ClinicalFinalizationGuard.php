<?php

declare(strict_types=1);

namespace App\Modules\Doctor\Application\Services;

use App\Modules\Doctor\Application\Contracts\ClinicalReopenGuard;
use App\Modules\Doctor\Application\Contracts\ClinicalStatusSource;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ClinicalFinalizationGuard implements ClinicalReopenGuard
{
    public function initialize(string $case): void
    {
        if (DB::transactionLevel() < 1) {
            throw new DomainException('clinical_status_unknown');
        }
        DB::table('doctor_clinical_guards')->insertOrIgnore(['case_id' => $case, 'status' => 'unknown', 'revision' => 0]);
        DB::table('doctor_clinical_guards')->where('case_id', $case)->lockForUpdate()->first();
    }

    public function refresh(string $case, string $binding, string $handoff): string
    {
        if (DB::transactionLevel() !== 0) {
            throw new DomainException('clinical_status_unknown');
        }

        try {
            return DB::transaction(fn () => $this->readAndRecord($case, $binding, $handoff));
        } catch (\Throwable) {
            if (DB::transactionLevel() === 0 && DB::connection()->getPdo()->inTransaction()) {
                DB::connection()->getPdo()->rollBack();
            }
            throw new DomainException('clinical_status_unknown');
        }
    }

    public function permitReopen(string $case, string $binding, string $handoff): void
    {
        if (DB::transactionLevel() < 1 || $this->readAndRecord($case, $binding, $handoff) !== 'not_finalized') {
            throw new DomainException('clinical_status_unknown');
        }
    }

    private function readAndRecord(string $case, string $binding, string $handoff): string
    {
        $this->initialize($case);
        // A registry row is a lock and history pointer, never clinical authority.
        $e = app(ClinicalStatusSource::class)->readWhileLocked($case, $binding, $handoff);
        $now = app(Clock::class)->now();
        $absolute = is_string($e['observed_at'] ?? null) && preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})\z/', $e['observed_at']) === 1;
        try {
            $observed = new DateTimeImmutable($absolute ? $e['observed_at'] : '1900-01-01');
        } catch (\Throwable) {
            $observed = new DateTimeImmutable('1900-01-01');
        }
        $valid = $absolute && $e && ($e['case_id'] ?? null) === $case && ($e['binding'] ?? null) === $binding
            && ($e['handoff_id'] ?? null) === $handoff && in_array($e['status'] ?? null, ['not_finalized', 'finalized'], true)
            && ($e['verified'] ?? null) === true && ($e['current'] ?? null) === true
            && ($e['coordinated'] ?? null) === true && ($e['complete_sources'] ?? null) === true
            && ! empty($e['sources']) && ! empty($e['source_reference']) && ! empty($e['revision'])
            && $observed <= $now && $observed >= $now->modify('-30 seconds');
        $status = $valid ? $e['status'] : 'unknown';
        $row = DB::table('doctor_clinical_guards')->where('case_id', $case)->first();
        $revision = $row->revision + 1;
        DB::table('doctor_clinical_observations')->insert(['id' => (string) Str::uuid(), 'case_id' => $case, 'revision' => $revision, 'binding' => $binding, 'handoff_id' => $handoff, 'evidence' => json_encode(['status' => $status, 'source_evidence' => $e], JSON_THROW_ON_ERROR), 'recorded_at' => $now]);
        DB::table('doctor_clinical_guards')->where('case_id', $case)->update(['status' => $status, 'revision' => $revision]);
        app(AuditStore::class)->append(AuditEvent::fromContext(app(AuthenticatedContextProvider::class)->current(), 'doctor.clinical-guard.observed', 'doctor', 'success', $now, targetType: 'case', targetId: $case, metadata: ['revision' => $revision], newStateDigest: hash('sha256', json_encode([$status, $binding, $handoff], JSON_THROW_ON_ERROR))));

        return $status;
    }
}
