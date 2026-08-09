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
        Schema::table('operator_arrivals', function (Blueprint $table): void {
            $table->dateTime('occurrence_at')->change();
        });

        Schema::table('booking_status_events', function (Blueprint $table): void {
            $table->dateTime('occurred_at')->change();
            $table->dateTime('received_at')->change();
            $table->dateTime('created_at')->change();
            $table->dateTime('updated_at')->change();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dateTime('updated_at')->change();
        });

        Schema::table('examination_consents', function (Blueprint $table): void {
            $table->dateTime('signed_at')->change();
        });

        Schema::table('audit_events', function (Blueprint $table): void {
            $table->dateTime('occurred_at')->change();
            $table->dateTime('recorded_at')->change();
            $table->dateTime('created_at')->change();
            $table->dateTime('updated_at')->change();
        });

        Schema::table('outbox_messages', function (Blueprint $table): void {
            $table->dateTime('occurred_at')->change();
        });

        Schema::table('operator_paper_tickets', function (Blueprint $table): void {
            $table->dateTime('issued_at')->change();
            $table->dateTime('created_at')->change();
            $table->dateTime('updated_at')->change();
        });

        Schema::table('operator_queue_admissions', function (Blueprint $table): void {
            $table->dateTime('ready_at')->change();
            $table->dateTime('created_at')->change();
            $table->dateTime('updated_at')->change();
        });

        Schema::table('operator_queue_admission_history', function (Blueprint $table): void {
            $table->dateTime('occurred_at')->change();
            $table->dateTime('created_at')->change();
            $table->dateTime('updated_at')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('operator_arrivals') || ! Schema::hasTable('operator_paper_tickets')) {
            return;
        }

        $minimum = '1970-01-01 00:00:01';
        $maximum = '2038-01-19 03:14:07';

        if (
            DB::table('operator_arrivals')->whereNotBetween('occurrence_at', [$minimum, $maximum])->exists()
            || DB::table('booking_status_events')->where(function ($query) use ($minimum, $maximum): void {
                $query->whereNotBetween('occurred_at', [$minimum, $maximum])
                    ->orWhereNotBetween('received_at', [$minimum, $maximum])
                    ->orWhereNotBetween('created_at', [$minimum, $maximum])
                    ->orWhereNotBetween('updated_at', [$minimum, $maximum]);
            })->exists()
            || DB::table('bookings')->whereNotBetween('updated_at', [$minimum, $maximum])->exists()
            || DB::table('examination_consents')->whereNotBetween('signed_at', [$minimum, $maximum])->exists()
            || DB::table('audit_events')->where(function ($query) use ($minimum, $maximum): void {
                $query->whereNotBetween('occurred_at', [$minimum, $maximum])
                    ->orWhereNotBetween('recorded_at', [$minimum, $maximum])
                    ->orWhereNotBetween('created_at', [$minimum, $maximum])
                    ->orWhereNotBetween('updated_at', [$minimum, $maximum]);
            })->exists()
            || DB::table('outbox_messages')->whereNotBetween('occurred_at', [$minimum, $maximum])->exists()
            || DB::table('operator_paper_tickets')->where(function ($query) use ($minimum, $maximum): void {
                $query->whereNotBetween('issued_at', [$minimum, $maximum])
                    ->orWhereNotBetween('created_at', [$minimum, $maximum])
                    ->orWhereNotBetween('updated_at', [$minimum, $maximum]);
            })->exists()
            || DB::table('operator_queue_admissions')->where(function ($query) use ($minimum, $maximum): void {
                $query->whereNotBetween('ready_at', [$minimum, $maximum])
                    ->orWhereNotBetween('created_at', [$minimum, $maximum])
                    ->orWhereNotBetween('updated_at', [$minimum, $maximum]);
            })->exists()
            || DB::table('operator_queue_admission_history')->where(function ($query) use ($minimum, $maximum): void {
                $query->whereNotBetween('occurred_at', [$minimum, $maximum])
                    ->orWhereNotBetween('created_at', [$minimum, $maximum])
                    ->orWhereNotBetween('updated_at', [$minimum, $maximum]);
            })->exists()
        ) {
            throw new RuntimeException('Cannot roll back operator arrival and ticket instants while values exceed the MySQL TIMESTAMP range.');
        }

        Schema::table('operator_arrivals', function (Blueprint $table): void {
            $table->timestamp('occurrence_at')->change();
        });

        Schema::table('booking_status_events', function (Blueprint $table): void {
            $table->timestamp('occurred_at')->change();
            $table->timestamp('received_at')->change();
            $table->timestamp('created_at')->change();
            $table->timestamp('updated_at')->change();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->timestamp('updated_at')->change();
        });

        Schema::table('examination_consents', function (Blueprint $table): void {
            $table->timestamp('signed_at')->change();
        });

        Schema::table('audit_events', function (Blueprint $table): void {
            $table->timestamp('occurred_at')->change();
            $table->timestamp('recorded_at')->change();
            $table->timestamp('created_at')->change();
            $table->timestamp('updated_at')->change();
        });

        Schema::table('outbox_messages', function (Blueprint $table): void {
            $table->timestamp('occurred_at')->change();
        });

        Schema::table('operator_paper_tickets', function (Blueprint $table): void {
            $table->timestamp('issued_at')->change();
            $table->timestamp('created_at')->change();
            $table->timestamp('updated_at')->change();
        });

        Schema::table('operator_queue_admissions', function (Blueprint $table): void {
            $table->timestamp('ready_at')->change();
            $table->timestamp('created_at')->change();
            $table->timestamp('updated_at')->change();
        });

        Schema::table('operator_queue_admission_history', function (Blueprint $table): void {
            $table->timestamp('occurred_at')->change();
            $table->timestamp('created_at')->change();
            $table->timestamp('updated_at')->change();
        });
    }
};
