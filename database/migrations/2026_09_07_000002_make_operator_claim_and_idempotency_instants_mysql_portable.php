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
        Schema::table('operator_queue_admissions', function (Blueprint $table): void {
            $table->dateTime('claimed_at')->nullable()->change();
        });

        Schema::table('idempotent_consumptions', function (Blueprint $table): void {
            $table->dateTime('handled_at')->nullable()->change();
            $table->dateTime('created_at')->nullable()->change();
            $table->dateTime('updated_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('operator_queue_admissions') || ! Schema::hasTable('idempotent_consumptions')) {
            return;
        }

        $minimum = '1970-01-01 00:00:01';
        $maximum = '2038-01-19 03:14:07';

        if (
            DB::table('operator_queue_admissions')->where(function ($query) use ($minimum, $maximum): void {
                $query->whereNotNull('claimed_at')
                    ->whereNotBetween('claimed_at', [$minimum, $maximum]);
            })->exists()
            || DB::table('idempotent_consumptions')->where(function ($query) use ($minimum, $maximum): void {
                $query->where(function ($q) use ($minimum, $maximum): void {
                    $q->whereNotNull('handled_at')
                        ->whereNotBetween('handled_at', [$minimum, $maximum]);
                })->orWhere(function ($q) use ($minimum, $maximum): void {
                    $q->whereNotNull('created_at')
                        ->whereNotBetween('created_at', [$minimum, $maximum]);
                })->orWhere(function ($q) use ($minimum, $maximum): void {
                    $q->whereNotNull('updated_at')
                        ->whereNotBetween('updated_at', [$minimum, $maximum]);
                });
            })->exists()
        ) {
            throw new RuntimeException('Cannot roll back operator claim and idempotency instants while values exceed the MySQL TIMESTAMP range.');
        }

        Schema::table('operator_queue_admissions', function (Blueprint $table): void {
            $table->timestamp('claimed_at')->nullable()->change();
        });

        Schema::table('idempotent_consumptions', function (Blueprint $table): void {
            $table->timestamp('handled_at')->nullable()->change();
            $table->timestamp('created_at')->nullable()->change();
            $table->timestamp('updated_at')->nullable()->change();
        });
    }
};
