<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_gateway_capture_sets', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('submission_id', 191)->unique();
            $table->string('admission_id', 36)->unique();
            $table->string('booking_id', 36)->index();
            $table->string('member_schedule_id', 36)->index();
            $table->string('operator_site_id', 36)->index();
            $table->string('operator_profile_id', 36)->index();
            $table->string('fixture_pair_id', 191);
            $table->unsignedSmallInteger('radiograph_count');
            $table->string('status', 32)->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->foreign('admission_id')->references('id')->on('operator_queue_admissions')->restrictOnDelete();
            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $table->foreign('member_schedule_id')->references('id')->on('shift_schedules')->restrictOnDelete();
            $table->foreign('operator_site_id')->references('id')->on('operator_sites')->restrictOnDelete();
            $table->foreign('operator_profile_id')->references('id')->on('operator_profiles')->restrictOnDelete();
            $table->index(['operator_site_id', 'member_schedule_id', 'status'], 'image_gateway_capture_scope_index');
        });

        Schema::create('image_gateway_capture_objects', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('capture_set_id', 36);
            $table->string('object_type', 32);
            $table->unsignedSmallInteger('object_index')->default(0);
            $table->string('object_key', 191)->unique();
            $table->char('checksum', 64);
            $table->unsignedBigInteger('bytes');
            $table->string('format', 32);
            $table->timestamps();

            $table->foreign('capture_set_id')->references('id')->on('image_gateway_capture_sets')->restrictOnDelete();
            $table->unique(['capture_set_id', 'object_type', 'object_index'], 'image_gateway_capture_object_identity');
        });

        Schema::create('image_gateway_studies', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('capture_set_id', 36)->unique();
            $table->string('object_key', 191)->unique();
            $table->char('checksum', 64);
            $table->unsignedBigInteger('bytes');
            $table->string('format', 32);
            $table->string('study_instance_uid', 191)->unique();
            $table->string('series_instance_uid', 191);
            $table->string('sop_instance_uid', 191)->unique();
            $table->string('transfer_syntax', 64);
            $table->string('window_center', 64);
            $table->string('window_width', 64);
            $table->unsignedInteger('rows');
            $table->unsignedInteger('columns');
            $table->timestamps();

            $table->foreign('capture_set_id')->references('id')->on('image_gateway_capture_sets')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_gateway_studies');
        Schema::dropIfExists('image_gateway_capture_objects');
        Schema::dropIfExists('image_gateway_capture_sets');
    }
};
