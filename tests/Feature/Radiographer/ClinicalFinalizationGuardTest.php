<?php

declare(strict_types=1);

namespace Tests\Feature\Radiographer;

use App\Modules\Doctor\Application\Contracts\ClinicalStatusSource;
use App\Modules\Doctor\Application\Services\ClinicalFinalizationGuard;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ClinicalFinalizationGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    public function test_default_unknown_and_database_record_alone_cannot_authorize_reopen(): void
    {
        $guard = app(ClinicalFinalizationGuard::class);
        DB::transaction(fn () => $guard->initialize('case'));
        DB::table('doctor_clinical_guards')->where('case_id', 'case')->update(['status' => 'not_finalized']);
        $this->expectException(\DomainException::class);
        DB::transaction(fn () => $guard->permitReopen('case', 'binding', 'handoff'));
    }

    public function test_doctor_refresh_records_positive_evidence_without_creating_reporting_workflow(): void
    {
        $this->app->instance(ClinicalStatusSource::class, new class implements ClinicalStatusSource
        {
            public function readWhileLocked(string $case, string $binding, string $handoff): ?array
            {
                return ['case_id' => $case, 'binding' => $binding, 'handoff_id' => $handoff, 'status' => 'finalized', 'verified' => true, 'current' => true, 'coordinated' => true, 'complete_sources' => true, 'sources' => ['synthetic-only'], 'source_reference' => 'synthetic-final', 'revision' => '1', 'observed_at' => now()->toAtomString()];
            }
        });
        $guard = app(ClinicalFinalizationGuard::class);
        $this->assertSame('finalized', $guard->refresh('case', 'binding', 'handoff'));
        $this->assertSame('finalized', DB::table('doctor_clinical_guards')->value('status'));
        $this->assertSame(1, DB::table('doctor_clinical_observations')->count());
        $this->expectException(\DomainException::class);
        DB::transaction(fn () => $guard->permitReopen('case', 'binding', 'handoff'));
    }

    public function test_guard_refresh_rolls_back_observation_when_commit_fails(): void
    {
        $this->app['events']->listen(TransactionCommitting::class, function (): void {
            throw new \RuntimeException('synthetic-commit-failure');
        });
        try {
            app(ClinicalFinalizationGuard::class)->refresh('case', 'binding', 'handoff');
            $this->fail('Commit failure succeeded');
        } catch (\DomainException|\RuntimeException) {
        }
        $this->assertSame(0, DB::table('doctor_clinical_observations')->count());
    }

    public function test_only_exact_current_coordinated_verified_negative_evidence_permits(): void
    {
        $valid = ['case_id' => 'case', 'binding' => 'binding', 'handoff_id' => 'handoff', 'status' => 'not_finalized', 'verified' => true, 'current' => true, 'coordinated' => true, 'complete_sources' => true, 'sources' => ['synthetic-reporting'], 'source_reference' => 'synthetic-only', 'revision' => '1', 'observed_at' => now()->toAtomString()];
        foreach ([['status' => 'unknown'], ['status' => 'finalized'], ['verified' => false], ['current' => false], ['coordinated' => false], ['complete_sources' => false], ['sources' => []], ['case_id' => 'other'], ['binding' => 'stale'], ['handoff_id' => 'old'], ['observed_at' => '2000-01-01T00:00:00Z'], ['revision' => ''], ['observed_at' => '-1 second'], ['observed_at' => ''], []] as $change) {
            $evidence = array_replace($valid, $change);
            $this->app->instance(ClinicalStatusSource::class, new class($evidence) implements ClinicalStatusSource
            {
                public function __construct(private array $evidence) {}

                public function readWhileLocked(string $case, string $binding, string $handoff): ?array
                {
                    return $this->evidence;
                }
            });
            try {
                DB::transaction(fn () => app(ClinicalFinalizationGuard::class)->permitReopen('case', 'binding', 'handoff'));
                $this->assertSame([], $change, 'Unsafe evidence permitted reopen');
            } catch (\DomainException) {
                $this->assertNotSame([], $change);
            }
        }
        $this->assertSame(1, DB::table('doctor_clinical_observations')->count());
    }
}
