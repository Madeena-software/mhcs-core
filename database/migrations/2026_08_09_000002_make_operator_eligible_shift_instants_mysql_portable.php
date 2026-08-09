<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operator_eligible_shifts', function (Blueprint $table): void {
            $table->dateTime('schedule_starts_at')->change();
            $table->dateTime('schedule_ends_at')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' && DB::table('operator_eligible_shifts')->where(function ($query): void {
            $query
                ->where('schedule_starts_at', '>', '2038-01-19 03:14:07')
                ->orWhere('schedule_ends_at', '>', '2038-01-19 03:14:07')
                ->orWhere('schedule_starts_at', '<', '1970-01-01 00:00:01')
                ->orWhere('schedule_ends_at', '<', '1970-01-01 00:00:01');
        })->exists()) {
            throw new RuntimeException('Cannot roll back operator eligible-shift schedule instants while values exceed the MySQL TIMESTAMP range.');
        }

        Schema::table('operator_eligible_shifts', function (Blueprint $table): void {
            $table->timestamp('schedule_starts_at')->change();
            $table->timestamp('schedule_ends_at')->change();
        });
    }
};
