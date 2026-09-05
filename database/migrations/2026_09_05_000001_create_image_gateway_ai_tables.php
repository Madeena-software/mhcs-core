<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_gateway_ai_jobs', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('study_id', 36)->unique();
            $table->string('capture_set_id', 36)->index();
            $table->string('booking_id', 36)->index();
            $table->string('member_id', 36)->index();
            $table->string('admission_id', 36)->nullable()->index();
            $table->string('status', 32)->default('queued')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->string('processing_claim_id', 36)->nullable();
            $table->timestamp('processing_lease_expires_at')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->text('last_error_message')->nullable();
            $table->string('correlation_id', 191)->nullable()->index();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->foreign('study_id')->references('id')->on('image_gateway_studies')->restrictOnDelete();
            $table->foreign('capture_set_id')->references('id')->on('image_gateway_capture_sets')->restrictOnDelete();
            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $table->foreign('member_id')->references('id')->on('members')->restrictOnDelete();
            $table->foreign('admission_id')->references('id')->on('operator_queue_admissions')->restrictOnDelete();
        });

        Schema::create('image_gateway_ai_reports', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('ai_job_id', 36)->unique();
            $table->string('study_id', 36)->index();
            $table->string('capture_set_id', 36)->index();
            $table->string('booking_id', 36)->index();
            $table->string('member_id', 36)->index();
            $table->string('original_object_key', 191)->nullable()->unique();
            $table->char('original_checksum', 64)->nullable();
            $table->unsignedBigInteger('original_bytes')->nullable();
            $table->string('original_filename', 191)->nullable();
            $table->string('derived_object_key', 191)->nullable()->unique();
            $table->char('derived_checksum', 64)->nullable();
            $table->unsignedBigInteger('derived_bytes')->nullable();
            $table->string('derived_filename', 191)->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->string('language', 10)->default('id');
            $table->string('clinical_disclaimer', 255)->nullable();
            $table->json('findings_summary')->nullable();
            $table->timestamps();

            $table->foreign('ai_job_id')->references('id')->on('image_gateway_ai_jobs')->restrictOnDelete();
            $table->foreign('study_id')->references('id')->on('image_gateway_studies')->restrictOnDelete();
            $table->foreign('capture_set_id')->references('id')->on('image_gateway_capture_sets')->restrictOnDelete();
            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $table->foreign('member_id')->references('id')->on('members')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_gateway_ai_reports');
        Schema::dropIfExists('image_gateway_ai_jobs');
    }
};
