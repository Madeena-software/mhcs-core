<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_workspace_managers', function (Blueprint $t): void {
            $t->string('user_id', 36);
            $t->string('site_id', 36);
            $t->boolean('active')->default(false);
            $t->primary(['user_id', 'site_id']);
        });
        Schema::create('doctor_eligibility', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('user_id', 36)->index();
            $t->string('site_id', 36);
            $t->string('service_id', 36);
            $t->string('modality', 16);
            $t->unsignedInteger('revision');
            $t->string('status', 24);
            $t->string('evidence_reference');
            $t->string('verifier_id', 36);
            $t->timestamp('verified_at');
            $t->timestamp('valid_until');
            $t->unique(['user_id', 'site_id', 'service_id', 'modality', 'revision'], 'doctor_eligibility_revision');
        });
        Schema::create('doctor_workspaces', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('handoff_id', 36)->unique();
            $t->string('binding', 64);
            $t->string('booking_id', 36)->index();
            foreach (['study_id', 'admission_id', 'site_id', 'member_schedule_id', 'service_id', 'user_id', 'eligibility_id'] as $c) {
                $t->string($c, 36);
            }
            $t->string('modality', 16);
            $t->unsignedInteger('generation')->default(1);
            $t->unsignedInteger('revision')->default(0);
            $t->timestamp('starts_at');
            $t->timestamp('expires_at');
            $t->boolean('revoked')->default(false);
            $t->string('clinical_status', 24);
            $t->string('quality', 24)->nullable();
            $t->uuid('draft_id')->nullable();
            $t->uuid('final_id')->nullable();
            $t->longText('snapshot');
        });
        Schema::create('doctor_report_versions', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('workspace_id')->index();
            $t->unsignedInteger('generation');
            $t->unsignedInteger('revision');
            $t->string('author_id', 36);
            $t->string('binding', 64);
            $t->string('handoff_id', 36);
            $t->longText('findings');
            $t->longText('conclusion');
            $t->longText('recommendations');
            $t->string('digest', 64);
            $t->timestamp('created_at');
            $t->unique(['workspace_id', 'revision']);
        });
        Schema::create('doctor_finalizations', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('workspace_id')->unique();
            $t->string('case_id', 36)->unique();
            $t->uuid('report_id')->unique();
            $t->unsignedInteger('revision');
            $t->string('author_id', 36);
            $t->string('finalizer_id', 36);
            $t->string('binding', 64);
            $t->string('handoff_id', 36);
            $t->string('digest', 64);
            $t->string('operation_key', 64);
            $t->string('session_digest', 64);
            $t->string('attestation');
            $t->timestamp('finalized_at');
        });
        Schema::create('doctor_workspace_history', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('workspace_id')->index();
            $t->string('actor_id', 36);
            $t->string('action', 32);
            $t->unsignedInteger('generation');
            $t->unsignedInteger('revision');
            $t->longText('data');
            $t->timestamp('occurred_at');
        });
    }

    public function down(): void
    {
        foreach (['doctor_workspace_history', 'doctor_finalizations', 'doctor_report_versions', 'doctor_workspaces', 'doctor_eligibility', 'doctor_workspace_managers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
