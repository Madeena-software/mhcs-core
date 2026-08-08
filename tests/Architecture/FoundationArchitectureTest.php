<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Shared\Topology\ModuleRegistry;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class FoundationArchitectureTest extends TestCase
{
    public function test_all_module_boundaries_are_registered_during_boot(): void
    {
        $this->assertSame(config('mhcs.modules'), app(ModuleRegistry::class)->all());

        foreach (['Member', 'Operator', 'Doctor', 'ImageGateway'] as $module) {
            $this->assertDirectoryExists(app_path("Modules/{$module}"));
            $this->assertDirectoryExists(app_path("Modules/{$module}/Application/Contracts"));
            $this->assertDirectoryExists(app_path("Modules/{$module}/Domain"));
            $this->assertDirectoryExists(app_path("Modules/{$module}/Infrastructure"));
            $this->assertDirectoryExists(app_path("Modules/{$module}/Presentation"));
        }

        $this->assertDirectoryExists(app_path('Shared'));
        $this->assertDirectoryExists(database_path('migrations'));
        $this->assertDirectoryExists(base_path('tests/Architecture'));
        $this->assertDirectoryExists(base_path('tests/Integration'));
    }

    public function test_shared_php_code_does_not_depend_on_modules(): void
    {
        foreach ($this->phpFiles(app_path('Shared')) as $file) {
            $this->assertStringNotContainsString(
                'App\\Modules\\',
                $file->getContents(),
                $file->getPathname(),
            );
        }
    }

    public function test_modules_do_not_reference_another_modules_internal_layers(): void
    {
        $violations = [];

        foreach (glob(app_path('Modules/*'), GLOB_ONLYDIR) as $modulePath) {
            $module = basename($modulePath);

            foreach ($this->phpFiles($modulePath) as $file) {
                preg_match_all(
                    '/App\\\\Modules\\\\(?<target>[A-Za-z_][A-Za-z0-9_]*)(?:\\\\(?<reference>[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*))?/',
                    $file->getContents(),
                    $matches,
                    PREG_SET_ORDER,
                );

                foreach ($matches as $match) {
                    if ($module === $match['target']) {
                        continue;
                    }

                    $reference = $match['reference'] ?? '';

                    if (preg_match(
                        '/^Application\\\\Contracts\\\\[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/',
                        $reference,
                    ) !== 1) {
                        $violations[] = sprintf('%s: %s', $file->getPathname(), $match[0]);
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Cross-module references must use Application\\Contracts only:\n".implode("\n", $violations),
        );
    }

    public function test_module_code_has_no_network_or_external_adapter_implementation(): void
    {
        foreach ($this->phpFiles(app_path('Modules')) as $file) {
            $contents = strtolower($file->getContents());

            $this->assertStringNotContainsString('guzzlehttp', $contents, $file->getPathname());
            $this->assertStringNotContainsString('curl_', $contents, $file->getPathname());
            $this->assertStringNotContainsString('http::', $contents, $file->getPathname());
            if ($file->getPathname() !== app_path('Modules/ImageGateway/Infrastructure/ImageWorkerBoundary.php')) {
                $this->assertStringNotContainsString('mpips', $contents, $file->getPathname());
                $this->assertStringNotContainsString('npz', $contents, $file->getPathname());
            }
        }
    }

    public function test_no_module_business_implementation_or_business_migrations_exist(): void
    {
        foreach (glob(app_path('Modules/*'), GLOB_ONLYDIR) as $modulePath) {
            foreach (['Controllers', 'Jobs', 'Models', 'Notifications', 'Policies'] as $forbidden) {
                $this->assertDirectoryDoesNotExist($modulePath.'/'.$forbidden);
            }
        }

        $migrations = array_map(
            static fn ($file): string => basename($file),
            glob(database_path('migrations/*.php'), GLOB_NOSORT),
        );

        $allowed = [
            '0001_01_01_000000_create_users_table.php',
            '0001_01_01_000001_create_cache_table.php',
            '0001_01_01_000002_create_jobs_table.php',
            '2026_08_04_000003_create_outbox_messages_table.php',
            '2026_08_04_000004_create_idempotent_consumptions_table.php',
            '2026_08_04_000005_add_security_state_to_users_table.php',
            '2026_08_04_000006_create_audit_events_table.php',
            '2026_08_04_000007_migrate_users_to_uuid.php',
            '2026_08_04_000008_create_member_identity_tables.php',
            '2026_08_05_000001_add_mvp01_profile_fields_to_members.php',
            '2026_08_05_000002_create_authorization_assignment_tables.php',
            '2026_08_05_000003_create_mvp03_booking_tables.php',
            '2026_08_05_000004_create_mvp04_operator_foundation_tables.php',
            '2026_08_06_000001_create_mvp04b_identity_verification_tables.php',
            '2026_08_06_000002_add_mvp04b_identity_active_claim.php',
            '2026_08_07_000001_create_examination_consents_table.php',
            '2026_08_07_000002_create_operator_paper_tickets_table.php',
            '2026_08_07_000003_create_operator_queue_admissions_table.php',
            '2026_08_08_000001_add_atomic_claim_to_operator_queue_admissions_table.php',
            '2026_08_08_000002_create_mvp04j_vital_signs_tables.php',
            '2026_08_08_000003_allow_one_queue_admission_per_ticket_stage.php',
        ];

        $this->assertSame([], array_values(array_diff($migrations, $allowed)));

        $this->assertDirectoryDoesNotExist(app_path('Filament'));
    }

    public function test_member_boundary_contains_only_opaque_reference_contracts(): void
    {
        foreach ($this->phpFiles(app_path('Modules/Member')) as $file) {
            $contents = strtolower($file->getContents());

            foreach (['npz', 'dicom', 'binary', 'parser', 'conversion', 'guzzlehttp', 'curl_'] as $term) {
                $this->assertStringNotContainsString($term, $contents, $file->getPathname());
            }
        }
    }

    /** @return list<\SplFileInfo> */
    private function phpFiles(string $path): array
    {
        return array_values(array_filter(
            File::allFiles($path),
            static fn (\SplFileInfo $file): bool => $file->getExtension() === 'php',
        ));
    }
}
