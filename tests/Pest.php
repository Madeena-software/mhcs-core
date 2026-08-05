<?php

declare(strict_types=1);

use Tests\TestCase;

$browserRun = array_reduce(
    $_SERVER['argv'] ?? [],
    static fn (bool $browser, mixed $argument): bool => $browser || (is_string($argument) && str_contains(str_replace('\\', '/', $argument), '/tests/Browser/')),
    false,
);

if ($browserRun) {
    $browserDatabase = dirname(__DIR__).'/storage/framework/testing/mhcs-browser.sqlite';
    $browserDirectory = dirname($browserDatabase);

    if (! is_dir($browserDirectory)) {
        mkdir($browserDirectory, 0755, true);
    }

    touch($browserDatabase);
    putenv('DB_DATABASE='.$browserDatabase);
    $_ENV['DB_DATABASE'] = $browserDatabase;
    $_SERVER['DB_DATABASE'] = $browserDatabase;
}

uses(TestCase::class)->in('Browser');
