<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('families', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->text('encrypted_family_card_number');
            $table->string('family_card_lookup_digest', 64)->unique();
            $table->timestamps();
        });

        Schema::create('members', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('user_id', 36)->unique();
            $table->string('family_id', 36)->nullable()->index();
            $table->string('medical_record_number', 36)->unique();
            $table->string('identity_status', 32)->default('pending_verification')->index();
            $table->string('identity_document_type', 16);
            $table->text('encrypted_nik');
            $table->string('nik_lookup_digest', 64)->unique();
            $table->string('name');
            $table->date('birth_date');
            $table->string('administrative_gender', 32);
            $table->string('registration_source', 32);
            $table->string('phone')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('family_id')->references('id')->on('families')->restrictOnDelete();
        });

        Schema::create('member_external_identifiers', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('member_id', 36)->index();
            $table->string('namespace', 191);
            $table->string('value', 191);
            $table->timestamps();

            $table->unique(['namespace', 'value']);
            $table->foreign('member_id')->references('id')->on('members')->restrictOnDelete();
        });

        Schema::create('member_verification_assets', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('member_id', 36)->index();
            $table->string('type', 32);
            $table->string('private_object_key', 191);
            $table->string('checksum', 64);
            $table->unsignedBigInteger('bytes');
            $table->string('format', 64)->nullable();
            $table->string('review_status', 32)->default('pending')->index();
            $table->boolean('is_current')->default(false)->index();
            $table->string('uploaded_by_user_id', 36);
            $table->string('reviewed_by_user_id', 36)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('replaces_id', 36)->nullable();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->restrictOnDelete();
            $table->foreign('uploaded_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('reviewed_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('replaces_id')->references('id')->on('member_verification_assets')->restrictOnDelete();
            $table->index(['member_id', 'type', 'review_status']);
        });

        Schema::create('member_guardians', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('child_member_id', 36)->index();
            $table->string('guardian_member_id', 36)->index();
            $table->string('status', 32)->default('verified')->index();
            $table->string('verified_by_user_id', 36);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['child_member_id', 'guardian_member_id', 'starts_at'], 'member_guardian_relation_unique');
            $table->foreign('child_member_id')->references('id')->on('members')->restrictOnDelete();
            $table->foreign('guardian_member_id')->references('id')->on('members')->restrictOnDelete();
            $table->foreign('verified_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('member_operations', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('operation_type', 64);
            $table->string('operation_id', 191);
            $table->string('payload_hash', 64);
            $table->string('status', 32)->default('pending')->index();
            $table->json('result')->nullable();
            $table->timestamps();

            $table->unique(['operation_type', 'operation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_operations');
        Schema::dropIfExists('member_guardians');
        Schema::dropIfExists('member_verification_assets');
        Schema::dropIfExists('member_external_identifiers');
        Schema::dropIfExists('members');
        Schema::dropIfExists('families');
    }
};
