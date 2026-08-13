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
            $table->string('radiograph_checksum', 64)->nullable();
            $table->string('gain_checksum', 64)->nullable();
            $table->string('radiograph_status', 16)->default('pending')->index();
            $table->string('gain_status', 16)->default('pending')->index();
            $table->string('mpips_status', 16)->default('pending')->index();
            $table->string('dicom_status', 16)->default('pending')->index();
        });
    }

    public function down(): void
    {
        Schema::table('image_gateway_capture_sets', function (Blueprint $table): void {
            $table->dropColumn([
                'radiograph_checksum',
                'gain_checksum',
                'radiograph_status',
                'gain_status',
                'mpips_status',
                'dicom_status',
            ]);
        });
    }
};
