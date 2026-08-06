<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_identity_verifications', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('arrival_id', 36)->unique();
            $table->string('booking_id', 36)->index();
            $table->string('member_schedule_id', 36)->index();
            $table->string('operator_site_id', 36)->index();
            $table->string('operator_profile_id', 36)->index();
            $table->string('state', 32)->index();
            $table->timestamp('started_at');
            $table->timestamp('decided_at')->nullable();
            $table->string('reason_category', 64)->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('operation_id', 191)->unique();
            $table->timestamps();

            $table->foreign('arrival_id')->references('id')->on('operator_arrivals')->restrictOnDelete();
            $table->foreign('operator_site_id')->references('id')->on('operator_sites')->restrictOnDelete();
            $table->foreign('operator_profile_id')->references('id')->on('operator_profiles')->restrictOnDelete();
            $table->index(['operator_profile_id', 'state'], 'operator_identity_profile_state_index');
            $table->index(['operator_site_id', 'state'], 'operator_identity_site_state_index');
        });

        Schema::create('operator_identity_verification_events', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('verification_id', 36)->index();
            $table->string('event_type', 64);
            $table->string('from_state', 32)->nullable();
            $table->string('to_state', 32)->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('operation_id', 191)->unique();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->foreign('verification_id')->references('id')->on('operator_identity_verifications')->restrictOnDelete();
            $table->index(['verification_id', 'event_type', 'occurred_at'], 'operator_identity_event_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_identity_verification_events');
        Schema::dropIfExists('operator_identity_verifications');
    }
};
