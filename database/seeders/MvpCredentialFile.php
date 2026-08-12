<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Support\Facades\File;

final class MvpCredentialFile
{
    public static function reset(string $email, string $password): void
    {
        if (! app()->environment('local')) {
            return;
        }

        self::write("MHCS local synthetic credentials\n\n".$email.': '.$password."\n");
    }

    public static function append(string $email, string $password): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $path = base_path('credential.txt');
        File::append($path, $email.': '.$password."\n");
        chmod($path, 0600);
    }

    private static function write(string $contents): void
    {
        $path = base_path('credential.txt');
        File::put($path, $contents, true);
        chmod($path, 0600);
    }
}
