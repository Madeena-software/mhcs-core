<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_mcu_examinations', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('operator_queue_admission_id', 36)->unique();
            $table->string('member_id', 36)->index();
            $table->string('booking_id', 36)->unique();
            $table->string('member_schedule_id', 36)->index();
            $table->string('operator_profile_id', 36)->index();
            $table->string('operator_site_id', 36)->index();
            $table->timestamp('examined_at');
            $table->decimal('systolic_bp_mmhg', 8, 2);
            $table->decimal('diastolic_bp_mmhg', 8, 2);
            $table->decimal('weight_kg', 8, 2);
            $table->decimal('height_cm', 8, 2);
            $table->decimal('temperature_c', 8, 2);
            $table->decimal('bmi', 8, 2);
            $table->decimal('glucose_mg_dl', 8, 2);
            $table->decimal('total_cholesterol_mg_dl', 8, 2);
            $table->decimal('uric_acid_mg_dl', 8, 2);
            $table->string('fasting_status', 16);
            $table->decimal('fasting_duration_hours', 6, 2)->nullable();
            $table->time('last_meal_at')->nullable();
            $table->decimal('pef_attempt_i', 8, 2)->nullable();
            $table->decimal('pef_attempt_ii', 8, 2)->nullable();
            $table->decimal('pef_attempt_iii', 8, 2)->nullable();
            $table->decimal('pef_highest_value', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('operation_id', 191)->unique();
            $table->timestamps();

            $table->foreign('operator_queue_admission_id')->references('id')->on('operator_queue_admissions')->restrictOnDelete();
            $table->foreign('member_id')->references('id')->on('members')->restrictOnDelete();
            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $table->foreign('member_schedule_id')->references('id')->on('shift_schedules')->restrictOnDelete();
            $table->foreign('operator_profile_id')->references('id')->on('operator_profiles')->restrictOnDelete();
            $table->foreign('operator_site_id')->references('id')->on('operator_sites')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_mcu_examinations');
    }
};
