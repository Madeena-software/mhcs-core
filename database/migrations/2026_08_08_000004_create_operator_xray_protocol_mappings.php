<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_xray_protocol_mappings', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('service_offering_id', 36)->unique();
            $table->unsignedInteger('current_version');
            $table->string('service_code_snapshot', 64);
            $table->json('projection_identifiers');
            $table->string('published_by_user_id', 36);
            $table->timestamp('published_at');
            $table->timestamps();

            $table->foreign('service_offering_id')->references('id')->on('service_offerings')->restrictOnDelete();
            $table->foreign('published_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('operator_xray_protocol_versions', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('operator_xray_protocol_mapping_id', 36);
            $table->unsignedInteger('version');
            $table->string('service_code_snapshot', 64);
            $table->json('projection_identifiers');
            $table->string('published_by_user_id', 36);
            $table->timestamp('published_at');
            $table->timestamps();

            $table->foreign('operator_xray_protocol_mapping_id')->references('id')->on('operator_xray_protocol_mappings')->restrictOnDelete();
            $table->foreign('published_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['operator_xray_protocol_mapping_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_xray_protocol_versions');
        Schema::dropIfExists('operator_xray_protocol_mappings');
    }
};
