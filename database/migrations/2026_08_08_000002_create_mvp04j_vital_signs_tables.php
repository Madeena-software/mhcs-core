<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_vital_signs_assessments', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('member_id', 36)->index();
            $table->string('booking_id', 36)->unique();
            $table->string('member_schedule_id', 36)->index();
            $table->decimal('systolic_bp_value', 8, 2)->nullable();
            $table->string('systolic_bp_unit', 16);
            $table->string('systolic_bp_missing_reason', 32)->nullable();
            $table->decimal('diastolic_bp_value', 8, 2)->nullable();
            $table->string('diastolic_bp_unit', 16);
            $table->string('diastolic_bp_missing_reason', 32)->nullable();
            $table->decimal('temperature_value', 8, 2)->nullable();
            $table->string('temperature_unit', 16);
            $table->string('temperature_missing_reason', 32)->nullable();
            $table->decimal('height_value', 8, 2)->nullable();
            $table->string('height_unit', 16);
            $table->string('height_missing_reason', 32)->nullable();
            $table->decimal('weight_value', 8, 2)->nullable();
            $table->string('weight_unit', 16);
            $table->string('weight_missing_reason', 32)->nullable();
            $table->decimal('bmi_value', 8, 2)->nullable();
            $table->string('bmi_unit', 16);
            $table->string('bmi_missing_reason', 32)->nullable();
            $table->timestamp('effective_at');
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->restrictOnDelete();
            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $table->foreign('member_schedule_id')->references('id')->on('shift_schedules')->restrictOnDelete();
        });

        Schema::create('operator_vital_signs_executions', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('member_vital_signs_assessment_id', 36)->unique();
            $table->string('operator_queue_admission_id', 36)->unique();
            $table->string('operator_profile_id', 36)->index();
            $table->string('operator_site_id', 36)->index();
            $table->timestamp('occurred_at');
            $table->string('operation_id', 191)->unique();
            $table->timestamps();

            $table->foreign('member_vital_signs_assessment_id')->references('id')->on('member_vital_signs_assessments')->restrictOnDelete();
            $table->foreign('operator_queue_admission_id')->references('id')->on('operator_queue_admissions')->restrictOnDelete();
            $table->foreign('operator_profile_id')->references('id')->on('operator_profiles')->restrictOnDelete();
            $table->foreign('operator_site_id')->references('id')->on('operator_sites')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_vital_signs_executions');
        Schema::dropIfExists('member_vital_signs_assessments');
    }
};
