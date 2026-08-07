<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examination_consents', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('member_id', 36)->index();
            $table->string('booking_id', 36)->unique();
            $table->string('examination_site_id', 36)->index();
            $table->string('operator_site_id', 191);
            $table->string('form_name', 64);
            $table->string('form_version', 32);
            $table->string('signer_type', 32);
            $table->string('signer_member_id', 36);
            $table->boolean('signature_confirmed');
            $table->timestamp('signed_at');
            $table->string('confirmed_by_operator_id', 36);
            $table->timestamp('recorded_at');
            $table->string('idempotency_id', 191)->unique();
            $table->string('private_scan_object_key', 191)->nullable();
            $table->string('private_scan_checksum', 64)->nullable();
            $table->unsignedBigInteger('private_scan_bytes')->nullable();
            $table->string('private_scan_format', 64)->nullable();
            $table->string('status', 32)->default('confirmed')->index();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->restrictOnDelete();
            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $table->foreign('examination_site_id')->references('id')->on('examination_site_refs')->restrictOnDelete();
            $table->foreign('signer_member_id')->references('id')->on('members')->restrictOnDelete();
            $table->index(['member_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examination_consents');
    }
};
