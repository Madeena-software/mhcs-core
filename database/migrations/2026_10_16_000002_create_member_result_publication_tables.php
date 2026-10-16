<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_result_managers', function (Blueprint $t): void {
            $t->string('user_id', 36);
            $t->string('site_id', 36);
            $t->boolean('active')->default(false);
            $t->primary(['user_id', 'site_id']);
        });
        Schema::create('member_result_cases', function (Blueprint $t): void {
            $t->string('case_id', 36)->primary();
            $t->unsignedInteger('revision')->default(0);
            $t->uuid('publication_id')->nullable();
        });
        Schema::create('member_result_publications', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('case_id', 36)->index();
            $t->unsignedInteger('generation');
            foreach (['member_id', 'user_id', 'clinician_id', 'site_id', 'report_id', 'finalization_id', 'handoff_id', 'publisher_id'] as $field) {
                $t->string($field, 36);
            }
            $t->string('digest', 64);
            $t->string('binding', 64);
            $t->string('identity_digest', 64);
            $t->string('state', 16);
            $t->timestamp('published_at');
            $t->timestamp('expires_at');
            $t->unique(['case_id', 'generation']);
        });
        Schema::create('member_result_history', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('case_id', 36);
            $t->uuid('publication_id')->nullable();
            $t->unsignedInteger('revision');
            $t->string('actor_id', 36);
            $t->string('action', 16);
            $t->string('operation_key', 64);
            $t->string('payload_digest', 64);
            $t->timestamp('occurred_at');
            $t->unique(['case_id', 'revision']);
            $t->unique(['case_id', 'operation_key']);
        });
    }

    public function down(): void
    {
        foreach (['member_result_history', 'member_result_publications', 'member_result_cases', 'member_result_managers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
