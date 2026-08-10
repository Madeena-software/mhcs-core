<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_paper_questionnaires', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('member_id', 36)->index();
            $table->string('booking_id', 36)->unique();
            $table->string('member_schedule_id', 36)->index();
            $table->string('examination_site_id', 36)->index();
            $table->string('operator_site_id', 191)->index();
            $table->string('operator_profile_id', 36)->index();
            $table->timestamp('completed_at');
            $table->string('form_version', 32);
            $table->string('private_photo_object_key', 191);
            $table->string('private_photo_checksum', 64);
            $table->unsignedBigInteger('private_photo_bytes');
            $table->string('private_photo_format', 64);
            $table->string('operation_id', 191)->unique();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->restrictOnDelete();
            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $table->foreign('member_schedule_id')->references('id')->on('shift_schedules')->restrictOnDelete();
            $table->foreign('examination_site_id')->references('id')->on('examination_site_refs')->restrictOnDelete();
            $table->foreign('operator_profile_id')->references('id')->on('operator_profiles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_paper_questionnaires');
    }
};
