<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! $this->isNumericUsersTable()) {
            return;
        }

        $legacyUsers = DB::table('users')->get();
        $legacySessions = Schema::hasTable('sessions') ? DB::table('sessions')->get() : collect();
        $userIds = [];

        foreach ($legacyUsers as $user) {
            $userIds[(string) $user->id] = (string) Str::uuid();
        }

        Schema::disableForeignKeyConstraints();
        Schema::rename('users', 'wp04_legacy_users');
        if (Schema::hasTable('sessions')) {
            Schema::rename('sessions', 'wp04_legacy_sessions');
        }

        $this->createUsersTable();
        $this->createSessionsTable();

        foreach ($legacyUsers as $user) {
            DB::table('users')->insert([
                'id' => $userIds[(string) $user->id],
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'password' => $user->password,
                'remember_token' => $user->remember_token,
                'account_status' => $user->account_status ?? 'active',
                'login_enabled' => true,
                'must_change_password' => $user->must_change_password ?? false,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]);
        }

        foreach ($legacySessions as $session) {
            DB::table('sessions')->insert([
                'id' => $session->id,
                'user_id' => $session->user_id === null ? null : ($userIds[(string) $session->user_id] ?? null),
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'payload' => $session->payload,
                'last_activity' => $session->last_activity,
            ]);
        }

        Schema::dropIfExists('wp04_legacy_sessions');
        Schema::dropIfExists('wp04_legacy_users');
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        throw new RuntimeException('The WP-04 UUID user migration is forward-only.');
    }

    private function isNumericUsersTable(): bool
    {
        return in_array(Schema::getColumnType('users', 'id'), ['integer', 'int', 'bigint'], true);
    }

    private function createUsersTable(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('email')->nullable();
            $table->unique('email', 'wp04_users_email_unique');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('account_status', 32)->default('active');
            $table->index('account_status', 'wp04_users_account_status_index');
            $table->boolean('login_enabled')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->timestamps();
        });
    }

    private function createSessionsTable(): void
    {
        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('user_id', 36)->nullable();
            $table->index('user_id', 'wp04_sessions_user_id_index');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity');
            $table->index('last_activity', 'wp04_sessions_last_activity_index');
        });
    }
};
