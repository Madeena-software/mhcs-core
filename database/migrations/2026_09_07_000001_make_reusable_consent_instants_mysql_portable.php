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
        Schema::table('member_master_consents', function (Blueprint $table): void {
            $table->dateTime('signed_at')->change();
            $table->dateTime('withdrawn_at')->nullable()->change();
        });

        Schema::table('consent_visit_confirmations', function (Blueprint $table): void {
            $table->dateTime('confirmed_at')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('member_master_consents') || ! Schema::hasTable('consent_visit_confirmations')) {
            return;
        }

        $minimum = '1970-01-01 00:00:01';
        $maximum = '2038-01-19 03:14:07';

        if (
            DB::table('member_master_consents')->where(function ($query) use ($minimum, $maximum): void {
                $query->whereNotBetween('signed_at', [$minimum, $maximum])
                    ->orWhere(function ($q) use ($minimum, $maximum): void {
                        $q->whereNotNull('withdrawn_at')
                            ->whereNotBetween('withdrawn_at', [$minimum, $maximum]);
                    });
            })->exists()
            || DB::table('consent_visit_confirmations')->whereNotBetween('confirmed_at', [$minimum, $maximum])->exists()
        ) {
            throw new RuntimeException('Cannot roll back reusable consent instants while values exceed the MySQL TIMESTAMP range.');
        }

        Schema::table('member_master_consents', function (Blueprint $table): void {
            $table->timestamp('signed_at')->change();
            $table->timestamp('withdrawn_at')->nullable()->change();
        });

        Schema::table('consent_visit_confirmations', function (Blueprint $table): void {
            $table->timestamp('confirmed_at')->change();
        });
    }
};
