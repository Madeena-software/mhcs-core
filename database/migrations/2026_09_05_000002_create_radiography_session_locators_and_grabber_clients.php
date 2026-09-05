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
            $table->string('locator_code', 4)->nullable()->after('state');
            $table->index('locator_code', 'operator_queue_admission_locator_code_index');
        });

        Schema::create('radiography_session_locators', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('operator_queue_admission_id', 36)->unique();
            $table->string('operator_site_id', 36)->index();
            $table->string('member_schedule_id', 36)->index();
            $table->string('locator_code', 4)->index();
            $table->string('status', 32)->index();
            $table->string('active_key', 128)->nullable()->unique();
            $table->timestamp('allocated_at');
            $table->timestamp('invalidated_at')->nullable();
            $table->string('invalidation_reason', 64)->nullable();
            $table->timestamps();

            $table->foreign('operator_queue_admission_id')->references('id')->on('operator_queue_admissions')->cascadeOnDelete();
            $table->foreign('operator_site_id')->references('id')->on('operator_sites')->restrictOnDelete();
            $table->foreign('member_schedule_id')->references('id')->on('shift_schedules')->restrictOnDelete();
            $table->index(['operator_site_id', 'member_schedule_id', 'status', 'locator_code'], 'rad_loc_lookup_index');
        });

        Schema::create('grabber_clients', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('grabber_id', 64)->unique();
            $table->string('name', 255);
            $table->string('operator_site_id', 36)->index();
            $table->string('token_hash', 64)->index();
            $table->string('status', 32)->index();
            $table->timestamps();

            $table->foreign('operator_site_id')->references('id')->on('operator_sites')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grabber_clients');
        Schema::dropIfExists('radiography_session_locators');

        Schema::table('operator_queue_admissions', function (Blueprint $table): void {
            $table->dropIndex('operator_queue_admission_locator_code_index');
            $table->dropColumn('locator_code');
        });
    }
};
