<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Support\Facades\File;

final class MvpCredentialFile
{
    public static function reset(string $email, string $password): void
    {
        if (! app()->environment('local') && ! (bool) env('MHCS_ALLOW_PRODUCTION_MVP_SEED', false)) {
            return;
        }

        $header = app()->environment('production') || (bool) env('MHCS_ALLOW_PRODUCTION_MVP_SEED', false)
            ? "MHCS production bootstrap credentials\n\n"
            : "MHCS local synthetic credentials\n\n";

        self::write($header.$email.': '.$password."\n");
    }

    public static function append(string $email, string $password): void
    {
        if (! app()->environment('local') && ! (bool) env('MHCS_ALLOW_PRODUCTION_MVP_SEED', false)) {
            return;
        }

        $path = self::filePath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        File::append($path, $email.': '.$password."\n");
        @chmod($path, 0600);
    }

    private static function write(string $contents): void
    {
        $path = self::filePath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        File::put($path, $contents, true);
        @chmod($path, 0600);
    }

    public static function filePath(): string
    {
        if (app()->environment('production') || (bool) env('MHCS_ALLOW_PRODUCTION_MVP_SEED', false)) {
            $customPath = env('MHCS_BOOTSTRAP_CREDENTIAL_PATH');
            if (is_string($customPath) && $customPath !== '') {
                return $customPath;
            }

            return storage_path('app/private/bootstrap/credential-server.txt');
        }

        return base_path('credential.txt');
    }
}
