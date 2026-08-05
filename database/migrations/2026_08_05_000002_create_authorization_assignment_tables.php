<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorization_role_assignments', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('user_id', 36);
            $table->string('role', 191);
            $table->string('assigned_by_user_id', 36)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'role']);
            $table->index(['user_id', 'active']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('assigned_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('authorization_permission_assignments', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('user_id', 36);
            $table->string('permission', 191);
            $table->string('assigned_by_user_id', 36)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'permission']);
            $table->index(['user_id', 'active']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('assigned_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorization_permission_assignments');
        Schema::dropIfExists('authorization_role_assignments');
    }
};
