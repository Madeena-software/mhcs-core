<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_queue_admissions', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('operator_paper_ticket_id', 36)->unique();
            $table->string('operator_site_id', 36)->index();
            $table->string('member_schedule_id', 36)->index();
            $table->string('queue_class', 32);
            $table->string('stage', 64);
            $table->string('state', 32);
            $table->timestamp('ready_at');
            $table->timestamps();

            $table->foreign('operator_paper_ticket_id')->references('id')->on('operator_paper_tickets')->restrictOnDelete();
            $table->foreign('operator_site_id')->references('id')->on('operator_sites')->restrictOnDelete();
            $table->foreign('member_schedule_id')->references('id')->on('shift_schedules')->restrictOnDelete();
            $table->index(['operator_site_id', 'member_schedule_id', 'queue_class', 'stage', 'state'], 'operator_queue_admission_scope_index');
            $table->index(['operator_site_id', 'ready_at', 'id'], 'operator_queue_admission_fifo_index');
        });

        Schema::create('operator_queue_admission_history', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('operator_queue_admission_id', 36)->index('operator_queue_history_admission_index');
            $table->string('operator_profile_id', 36)->index();
            $table->string('event_type', 64);
            $table->string('from_state', 32)->nullable();
            $table->string('to_state', 32);
            $table->string('operation_id', 191)->unique();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->foreign('operator_queue_admission_id', 'operator_queue_history_admission_fk')->references('id')->on('operator_queue_admissions')->restrictOnDelete();
            $table->foreign('operator_profile_id')->references('id')->on('operator_profiles')->restrictOnDelete();
            $table->unique(['operator_queue_admission_id', 'event_type'], 'operator_queue_history_admission_event_unique');
            $table->index(['operator_queue_admission_id', 'occurred_at'], 'operator_queue_history_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_queue_admission_history');
        Schema::dropIfExists('operator_queue_admissions');
    }
};
