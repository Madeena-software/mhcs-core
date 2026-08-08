<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        if (! config('app.key')) {
            config(['app.key' => 'base64:'.base64_encode(str_repeat('0', 32))]);
        }

        foreach ([
            'mhcs.security.identifier_key' => 'mhcs-test-identifier-key',
            'mhcs.security.object_key' => 'mhcs-test-object-encryption-key',
            'mhcs.security.grant_key' => 'mhcs-test-access-grant-key',
        ] as $key => $fallback) {
            $value = config($key);
            if (! is_string($value) || trim($value) === '') {
                config([$key => $fallback]);
            }
        }

        return $app;
    }
}
