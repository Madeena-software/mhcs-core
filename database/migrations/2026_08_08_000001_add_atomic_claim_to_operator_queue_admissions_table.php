<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operator_queue_admissions', function (Blueprint $table): void {
            $table->string('operator_profile_id', 36)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->foreign('operator_profile_id')->references('id')->on('operator_profiles')->restrictOnDelete();
            $table->unique('operator_profile_id', 'operator_queue_admission_active_claim_profile_unique');
        });
    }

    public function down(): void
    {
        Schema::table('operator_queue_admissions', function (Blueprint $table): void {
            $table->dropForeign(['operator_profile_id']);
            $table->dropUnique('operator_queue_admission_active_claim_profile_unique');
            $table->dropColumn(['operator_profile_id', 'claimed_at']);
        });
    }
};
