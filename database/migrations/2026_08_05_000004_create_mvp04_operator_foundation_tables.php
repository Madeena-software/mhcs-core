<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_sites', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('operator_site_id', 191)->unique();
            $table->string('organization_id', 191)->index();
            $table->string('organization_name');
            $table->string('code', 64)->unique();
            $table->string('display_name');
            $table->text('address_line')->nullable();
            $table->string('timezone', 64);
            $table->boolean('active')->default(true)->index();
            $table->string('source_version', 64)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'active']);
            $table->index(['active', 'operator_site_id']);
        });

        Schema::create('operator_profiles', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('user_id', 36)->unique();
            $table->string('display_name')->nullable();
            $table->string('employee_code', 64)->nullable()->unique();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['user_id', 'active']);
        });

        Schema::create('operator_site_assignments', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('operator_profile_id', 36)->index();
            $table->string('operator_site_id', 36)->index();
            $table->boolean('active')->default(true)->index();
            $table->string('assigned_by_user_id', 36)->nullable()->index();
            $table->timestamp('assigned_at');
            $table->timestamp('revoked_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('operator_profile_id')->references('id')->on('operator_profiles')->restrictOnDelete();
            $table->foreign('operator_site_id')->references('id')->on('operator_sites')->restrictOnDelete();
            $table->foreign('assigned_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['operator_profile_id', 'active']);
            $table->index(['operator_site_id', 'active']);
            $table->index(['operator_profile_id', 'operator_site_id', 'active'], 'operator_site_assignment_context_index');
        });

        Schema::create('operator_eligible_shifts', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('member_schedule_id', 36)->unique();
            $table->string('operator_site_id', 191)->index();
            $table->timestamp('schedule_starts_at');
            $table->timestamp('schedule_ends_at');
            $table->unsignedInteger('confirmed_count_at_eligibility');
            $table->unsignedInteger('quota');
            $table->unsignedInteger('event_version');
            $table->string('source_event_id', 191)->unique();
            $table->timestamp('eligible_at');
            $table->string('sync_status', 32)->default('eligible')->index();
            $table->timestamps();

            $table->index(['operator_site_id', 'schedule_starts_at'], 'operator_eligible_site_schedule_index');
            $table->index(['operator_site_id', 'sync_status'], 'operator_eligible_active_site_index');
        });

        Schema::create('operator_shift_assignments', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('operator_eligible_shift_id', 36)->index();
            $table->string('operator_profile_id', 36)->index();
            $table->string('assigned_by_user_id', 36)->index();
            $table->string('status', 32)->default('active')->index();
            $table->timestamp('assigned_at');
            $table->timestamp('revoked_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('operator_eligible_shift_id')->references('id')->on('operator_eligible_shifts')->restrictOnDelete();
            $table->foreign('operator_profile_id')->references('id')->on('operator_profiles')->restrictOnDelete();
            $table->foreign('assigned_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['operator_eligible_shift_id', 'status'], 'operator_shift_active_assignment_index');
            $table->index(['operator_profile_id', 'status'], 'operator_profile_active_assignment_index');
        });

        Schema::create('operator_arrivals', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('booking_id', 36)->index();
            $table->string('member_schedule_id', 36)->index();
            $table->string('operator_site_id', 36)->index();
            $table->string('operator_profile_id', 36)->index();
            $table->timestamp('occurrence_at');
            $table->timestamp('recorded_at');
            $table->string('operation_id', 191)->unique();
            $table->string('source', 64);
            $table->string('status', 32)->default('recorded')->index();
            $table->timestamps();

            $table->foreign('operator_site_id')->references('id')->on('operator_sites')->restrictOnDelete();
            $table->foreign('operator_profile_id')->references('id')->on('operator_profiles')->restrictOnDelete();
            $table->index(['booking_id', 'status'], 'operator_arrival_booking_lookup_index');
            $table->index(['operator_site_id', 'occurrence_at'], 'operator_arrival_site_time_index');
            $table->index(['operator_profile_id', 'status'], 'operator_arrival_operator_status_index');
        });

        Schema::create('booking_status_events', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('booking_id', 36)->index();
            $table->string('source_service', 64);
            $table->string('source_operator_id', 36)->nullable();
            $table->string('event_type', 64);
            $table->timestamp('occurred_at');
            $table->timestamp('received_at');
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $table->index(['booking_id', 'event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_status_events');
        Schema::dropIfExists('operator_arrivals');
        Schema::dropIfExists('operator_shift_assignments');
        Schema::dropIfExists('operator_eligible_shifts');
        Schema::dropIfExists('operator_site_assignments');
        Schema::dropIfExists('operator_profiles');
        Schema::dropIfExists('operator_sites');
    }
};
