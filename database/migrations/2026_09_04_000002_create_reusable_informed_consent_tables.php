<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_master_consents', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('member_id', 36)->index();
            $table->unsignedInteger('consent_version');
            $table->string('form_name', 64)->default('Informed Consent');
            $table->string('form_version', 32)->default('V1');
            $table->string('screening_scope', 64)->default('radiography_screening');
            $table->string('signer_type', 32)->default('member');
            $table->string('signer_member_id', 36);
            $table->timestamp('signed_at');
            $table->string('status', 32)->default('active')->index();
            $table->timestamp('withdrawn_at')->nullable();
            $table->text('withdrawn_reason')->nullable();
            $table->string('withdrawn_by_operator_id', 36)->nullable();
            $table->string('created_by_operator_id', 36);
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->restrictOnDelete();
            $table->foreign('signer_member_id')->references('id')->on('members')->restrictOnDelete();
            $table->unique(['member_id', 'consent_version'], 'member_master_consent_version_unique');
            $table->index(['member_id', 'screening_scope', 'status'], 'member_master_consent_scope_index');
        });

        Schema::create('consent_visit_confirmations', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('booking_id', 36)->unique();
            $table->string('member_id', 36)->index();
            $table->string('member_master_consent_id', 36)->index();
            $table->string('examination_site_id', 36)->index();
            $table->string('operator_site_id', 191);
            $table->string('confirmed_by_operator_id', 36);
            $table->timestamp('confirmed_at');
            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $table->foreign('member_id')->references('id')->on('members')->restrictOnDelete();
            $table->foreign('member_master_consent_id')->references('id')->on('member_master_consents')->restrictOnDelete();
            $table->foreign('examination_site_id')->references('id')->on('examination_site_refs')->restrictOnDelete();
            $table->index(['member_id', 'confirmed_at'], 'consent_visit_member_confirmed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_visit_confirmations');
        Schema::dropIfExists('member_master_consents');
    }
};
