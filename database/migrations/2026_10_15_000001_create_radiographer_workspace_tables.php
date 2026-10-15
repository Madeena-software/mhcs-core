<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_radiographer_eligibility', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('user_id', 36)->index();
            $t->string('site_id', 36)->index();
            $t->unsignedInteger('revision');
            $t->string('status', 24);
            $t->string('evidence_reference');
            $t->string('verifying_authority');
            $t->timestamp('verified_at');
            $t->unique(['user_id', 'site_id', 'revision'], 'radiographer_eligibility_revision');
        });
        Schema::create('operator_workspace_protocols', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('study_id', 36)->index();
            $t->unsignedInteger('revision');
            $t->string('source_checksum', 64);
            $t->string('reference');
            $t->string('projection', 16);
            $t->string('description', 64);
            $t->string('accession', 16)->nullable();
            $t->string('study_reference', 16)->nullable();
            $t->string('laterality', 24);
            $t->boolean('complete')->default(false);
            $t->boolean('approved')->default(false);
            $t->string('verified_by');
            $t->timestamp('verified_at');
            $t->unique(['study_id', 'revision']);
        });
        Schema::create('operator_workspace_managers', function (Blueprint $t): void {
            $t->string('user_id', 36);
            $t->string('site_id', 36);
            $t->boolean('active')->default(false);
            $t->primary(['user_id', 'site_id']);
        });
        Schema::create('operator_radiographer_workspaces', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('study_id', 36)->unique();
            $t->string('booking_id', 36);
            $t->string('admission_id', 36);
            $t->string('site_id', 36)->index();
            $t->string('user_id', 36)->index();
            $t->unsignedInteger('generation')->default(1);
            $t->unsignedInteger('revision')->default(0);
            $t->unsignedInteger('cycle')->default(1);
            $t->timestamp('starts_at');
            $t->timestamp('expires_at');
            $t->boolean('revoked')->default(false);
            $t->string('binding', 64)->nullable();
            $t->longText('overlay')->nullable();
            $t->longText('qc')->nullable();
            $t->uuid('active_handoff')->nullable();
        });
        Schema::create('operator_radiographer_history', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('workspace_id')->index();
            $t->string('actor_id', 36);
            $t->string('action', 32);
            $t->unsignedInteger('generation');
            $t->unsignedInteger('revision');
            $t->unsignedInteger('cycle');
            $t->longText('data');
            $t->timestamp('occurred_at');
        });
        Schema::create('operator_radiographer_handoffs', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('workspace_id');
            $t->unsignedInteger('cycle');
            $t->string('actor_id', 36);
            $t->string('idempotency_key', 64);
            $t->string('payload_digest', 64);
            $t->string('binding', 64);
            $t->longText('snapshot');
            $t->timestamp('occurred_at');
            $t->unique(['workspace_id', 'cycle'], 'radiographer_one_handoff_per_cycle');
        });
        Schema::create('image_gateway_workspace_verifications', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('study_id', 36)->index();
            $t->string('binding', 64)->unique();
            $t->longText('snapshot');
            $t->timestamp('verified_at');
        });
        Schema::create('image_gateway_workspace_sop_claims', function (Blueprint $t): void {
            $t->string('sop_uid', 64)->primary();
            $t->string('study_id', 36);
            $t->string('checksum', 64);
        });
        Schema::create('doctor_clinical_guards', function (Blueprint $t): void {
            $t->string('case_id', 36)->primary();
            $t->string('status', 24)->default('unknown');
            $t->unsignedInteger('revision')->default(0);
        });
        Schema::create('doctor_clinical_observations', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('case_id', 36)->index();
            $t->unsignedInteger('revision');
            $t->string('binding', 64);
            $t->string('handoff_id', 36);
            $t->longText('evidence');
            $t->timestamp('recorded_at');
        });
    }

    public function down(): void
    {
        foreach (['doctor_clinical_observations', 'doctor_clinical_guards', 'image_gateway_workspace_sop_claims', 'image_gateway_workspace_verifications', 'operator_radiographer_handoffs', 'operator_radiographer_history', 'operator_radiographer_workspaces', 'operator_workspace_managers', 'operator_workspace_protocols', 'operator_radiographer_eligibility'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
