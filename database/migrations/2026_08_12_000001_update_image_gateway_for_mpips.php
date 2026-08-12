<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('image_gateway_capture_sets', function (Blueprint $table): void {
            $table->dropColumn('fixture_pair_id');
            $table->string('processing_status', 32)->default('completed')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('processing_claim_id', 36)->nullable();
            $table->timestamp('processing_lease_expires_at')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->unsignedSmallInteger('last_response_status')->nullable();
            $table->string('conversion_job_id', 36)->nullable()->index();
            $table->string('correlation_id', 36)->nullable();
            $table->char('manifest_checksum', 64)->nullable();
            $table->unsignedBigInteger('manifest_bytes')->nullable();
            $table->char('signature_checksum', 64)->nullable();
            $table->unsignedBigInteger('signature_bytes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
        });

        Schema::table('image_gateway_studies', function (Blueprint $table): void {
            $table->string('filename', 191)->default('capture.dcm');
            $table->string('transfer_syntax', 64)->nullable()->change();
            $table->string('window_center', 64)->nullable()->change();
            $table->string('window_width', 64)->nullable()->change();
            $table->unsignedInteger('rows')->nullable()->change();
            $table->unsignedInteger('columns')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('image_gateway_studies', function (Blueprint $table): void {
            $table->dropColumn('filename');
            $table->string('transfer_syntax', 64)->nullable(false)->change();
            $table->string('window_center', 64)->nullable(false)->change();
            $table->string('window_width', 64)->nullable(false)->change();
            $table->unsignedInteger('rows')->nullable(false)->change();
            $table->unsignedInteger('columns')->nullable(false)->change();
        });

        Schema::table('image_gateway_capture_sets', function (Blueprint $table): void {
            $table->string('fixture_pair_id', 191)->default('retired');
            $table->dropColumn([
                'processing_status',
                'attempts',
                'processing_claim_id',
                'processing_lease_expires_at',
                'last_error_code',
                'last_response_status',
                'conversion_job_id',
                'correlation_id',
                'manifest_checksum',
                'manifest_bytes',
                'signature_checksum',
                'signature_bytes',
                'completed_at',
                'failed_at',
            ]);
        });
    }
};
