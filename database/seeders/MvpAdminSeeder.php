<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

final class MvpAdminSeeder extends Seeder
{
    private const EMAIL = 'mvp-admin@example.test';

    /** @var list<string> */
    private const PERMISSIONS = [
        'member.admin.access',
        'member.account.read',
        'member.account.manage',
        'member.audit.read',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('MvpAdminSeeder is limited to local and testing environments.');
        }

        $existing = User::query()->where('email', self::EMAIL)->first();

        if ($existing !== null) {
            $this->assertExisting($existing);
            $this->command?->info(self::EMAIL.' already exists; its credential and claims were not changed.');

            return;
        }

        $plaintext = bin2hex(random_bytes(24));
        $userId = (string) Str::uuid();

        DB::transaction(function () use ($plaintext, $userId): void {
            $now = now();
            DB::table('users')->insert([
                'id' => $userId,
                'email' => self::EMAIL,
                'email_verified_at' => null,
                'password' => Hash::make($plaintext),
                'remember_token' => null,
                'account_status' => 'active',
                'login_enabled' => true,
                'must_change_password' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('authorization_role_assignments')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'role' => 'administrator',
                'assigned_by_user_id' => null,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (self::PERMISSIONS as $permission) {
                DB::table('authorization_permission_assignments')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'permission' => $permission,
                    'assigned_by_user_id' => null,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        if ($this->command !== null && defined('STDIN') && stream_isatty(STDIN)) {
            $this->command->line(self::EMAIL.' development-only credential (show once): '.$plaintext);
        }
    }

    private function assertExisting(User $user): void
    {
        if (
            $user->account_status !== 'active'
            || ! $user->login_enabled
            || $user->must_change_password
            || DB::table('members')->where('user_id', $user->getKey())->exists()
        ) {
            throw new RuntimeException('The existing MVP admin account has inconsistent account or Member state.');
        }

        $roles = DB::table('authorization_role_assignments')->where('user_id', $user->getKey())->get();
        if ($roles->count() !== 1 || $roles->first()->role !== 'administrator' || ! $roles->first()->active || $roles->first()->assigned_by_user_id !== null) {
            throw new RuntimeException('The existing MVP admin account has inconsistent role assignments.');
        }

        $permissions = DB::table('authorization_permission_assignments')->where('user_id', $user->getKey())->get();
        $actual = $permissions->map(static fn (object $row): string => (string) $row->permission)->sort()->values()->all();
        $expected = collect(self::PERMISSIONS)->sort()->values()->all();

        if (
            $permissions->count() !== count(self::PERMISSIONS)
            || $actual !== $expected
            || $permissions->contains(static fn (object $row): bool => ! $row->active || $row->assigned_by_user_id !== null)
        ) {
            throw new RuntimeException('The existing MVP admin account has inconsistent permission assignments.');
        }
    }
}
