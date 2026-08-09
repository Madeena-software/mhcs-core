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
        Schema::table('shift_schedules', function (Blueprint $table): void {
            $table->dateTime('starts_at')->change();
            $table->dateTime('ends_at')->change();
            $table->dateTime('eligible_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' && DB::table('shift_schedules')->where(function ($query): void {
            $query
                ->where('starts_at', '>', '2038-01-19 03:14:07')
                ->orWhere('ends_at', '>', '2038-01-19 03:14:07')
                ->orWhere('eligible_at', '>', '2038-01-19 03:14:07')
                ->orWhere('starts_at', '<', '1970-01-01 00:00:01')
                ->orWhere('ends_at', '<', '1970-01-01 00:00:01')
                ->orWhere('eligible_at', '<', '1970-01-01 00:00:01');
        })->exists()) {
            throw new RuntimeException('Cannot roll back shift schedule instants while values exceed the MySQL TIMESTAMP range.');
        }

        Schema::table('shift_schedules', function (Blueprint $table): void {
            $table->timestamp('starts_at')->change();
            $table->timestamp('ends_at')->change();
            $table->timestamp('eligible_at')->nullable()->change();
        });
    }
};
