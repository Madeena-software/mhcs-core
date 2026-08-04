<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->string('event_id', 191)->primary();
            $table->unsignedInteger('event_version');
            $table->string('actor_id', 191)->nullable()->index();
            $table->string('session_id', 191)->nullable();
            $table->json('roles');
            $table->json('permissions');
            $table->string('site_id', 191)->nullable()->index();
            $table->string('case_id', 191)->nullable()->index();
            $table->string('target_type', 191)->nullable();
            $table->string('target_id', 191)->nullable();
            $table->string('action', 191);
            $table->string('previous_state_digest', 64)->nullable();
            $table->string('new_state_digest', 64)->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('recorded_at');
            $table->string('correlation_id', 191)->nullable()->index();
            $table->string('source', 191);
            $table->string('outcome', 64);
            $table->json('metadata');
            $table->timestamps();

            $table->index(['action', 'occurred_at']);
            $table->index(['source', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
