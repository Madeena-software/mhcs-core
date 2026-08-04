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
            $this->assertStringNotContainsString('mpips', $contents, $file->getPathname());
            $this->assertStringNotContainsString('npz', $contents, $file->getPathname());
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

        $this->assertCount(5, $migrations);
        $this->assertSame(
            [],
            array_values(array_filter($migrations, static fn (string $file): bool => str_contains(
                $file,
                'create_'
            ) && ! str_contains($file, 'users_table') && ! str_contains($file, 'cache_table') && ! str_contains($file, 'jobs_table') && ! str_contains($file, 'outbox_messages') && ! str_contains($file, 'idempotent_consumptions'))),
        );

        $this->assertDirectoryDoesNotExist(app_path('Providers/Filament'));
        $this->assertDirectoryDoesNotExist(app_path('Filament'));
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
