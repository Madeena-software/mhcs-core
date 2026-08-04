<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('account_status', 32)->default('active')->index();
            $table->boolean('must_change_password')->default(false);
            $table->string('identifier_digest', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['identifier_digest']);
            $table->dropColumn(['account_status', 'must_change_password', 'identifier_digest']);
        });
    }
};
