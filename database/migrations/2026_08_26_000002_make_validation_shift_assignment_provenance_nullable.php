<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operator_shift_assignments', function (Blueprint $table): void {
            $table->string('assigned_by_user_id', 36)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('operator_shift_assignments', function (Blueprint $table): void {
            $table->string('assigned_by_user_id', 36)->nullable(false)->change();
        });
    }
};
