<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_organization_refs', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('operator_organization_id', 191)->unique();
            $table->string('name');
            $table->string('source_version', 64)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('examination_site_refs', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('operator_site_id', 191)->unique();
            $table->string('operator_organization_ref_id', 36)->index();
            $table->string('code', 64)->unique();
            $table->string('display_name');
            $table->string('timezone', 64);
            $table->string('source_version', 64)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->foreign('operator_organization_ref_id')
                ->references('id')->on('operator_organization_refs')->restrictOnDelete();
        });

        Schema::create('service_offerings', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->boolean('includes_ai')->default(false);
            $table->boolean('includes_doctor')->default(false);
            $table->decimal('point_price', 20, 4);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('point_exchange_rates', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->unsignedBigInteger('rupiah_per_point');
            $table->string('status', 32)->index();
            $table->timestamp('effective_at');
            $table->string('configured_by_admin_id', 36)->nullable()->index();
            $table->timestamps();

            $table->foreign('configured_by_admin_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('shift_schedules', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('examination_site_id', 36)->index();
            $table->string('service_offering_id', 36)->index();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('quota');
            $table->string('status', 32)->index();
            $table->timestamp('eligible_at')->nullable();
            $table->timestamps();

            $table->foreign('examination_site_id')->references('id')->on('examination_site_refs')->restrictOnDelete();
            $table->foreign('service_offering_id')->references('id')->on('service_offerings')->restrictOnDelete();
            $table->index(['examination_site_id', 'status', 'starts_at']);
        });

        Schema::create('bookings', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('member_id', 36)->index();
            $table->string('shift_schedule_id', 36)->index();
            $table->string('service_offering_id', 36)->index();
            $table->string('examination_site_id_snapshot', 36)->index();
            $table->string('booking_type', 16);
            $table->string('funding_source', 32);
            $table->string('status', 32)->index();
            $table->string('service_code_snapshot', 64);
            $table->decimal('point_cost_snapshot', 20, 4);
            $table->string('point_exchange_rate_id', 36);
            $table->boolean('includes_ai_snapshot');
            $table->boolean('includes_doctor_snapshot');
            $table->string('site_code_snapshot', 64);
            $table->string('site_name_snapshot');
            $table->string('site_timezone_snapshot', 64);
            $table->timestamp('created_at');
            $table->timestamp('confirmed_at');
            $table->timestamp('updated_at');

            $table->foreign('member_id')->references('id')->on('members')->restrictOnDelete();
            $table->foreign('shift_schedule_id')->references('id')->on('shift_schedules')->restrictOnDelete();
            $table->foreign('service_offering_id')->references('id')->on('service_offerings')->restrictOnDelete();
            $table->foreign('examination_site_id_snapshot')->references('id')->on('examination_site_refs')->restrictOnDelete();
            $table->foreign('point_exchange_rate_id')->references('id')->on('point_exchange_rates')->restrictOnDelete();
            $table->index(['member_id', 'status']);
            $table->index(['shift_schedule_id', 'status']);
        });

        Schema::create('point_ledger_entries', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('member_id', 36)->index();
            $table->string('booking_id', 36)->nullable()->index();
            $table->string('funding_source', 32)->index();
            $table->string('entry_type', 32);
            $table->decimal('point_delta', 20, 4);
            $table->string('source_reference', 191)->unique();
            $table->string('reverses_id', 36)->nullable();
            $table->timestamp('created_at');

            $table->foreign('member_id')->references('id')->on('members')->restrictOnDelete();
            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $table->foreign('reverses_id')->references('id')->on('point_ledger_entries')->restrictOnDelete();
            $table->index(['member_id', 'funding_source', 'created_at']);
        });

        Schema::create('local_imaging_orders', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('booking_id', 36)->unique();
            $table->string('member_id', 36)->index();
            $table->string('shift_schedule_id', 36)->index();
            $table->string('examination_site_id', 36)->index();
            $table->string('service_code_snapshot', 64);
            $table->string('status', 32)->index();
            $table->timestamp('authored_at');
            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $table->foreign('member_id')->references('id')->on('members')->restrictOnDelete();
            $table->foreign('shift_schedule_id')->references('id')->on('shift_schedules')->restrictOnDelete();
            $table->foreign('examination_site_id')->references('id')->on('examination_site_refs')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_imaging_orders');
        Schema::dropIfExists('point_ledger_entries');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('shift_schedules');
        Schema::dropIfExists('point_exchange_rates');
        Schema::dropIfExists('service_offerings');
        Schema::dropIfExists('examination_site_refs');
        Schema::dropIfExists('operator_organization_refs');
    }
};
