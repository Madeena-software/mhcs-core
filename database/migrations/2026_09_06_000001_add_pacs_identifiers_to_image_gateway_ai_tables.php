<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('image_gateway_ai_jobs', function (Blueprint $table): void {
            $table->unsignedBigInteger('pacs_sid')->nullable()->after('status')->index();
            $table->unsignedBigInteger('pacs_ai_calc_id')->nullable()->after('pacs_sid')->index();
        });

        Schema::table('image_gateway_ai_reports', function (Blueprint $table): void {
            $table->unsignedBigInteger('pacs_sid')->nullable()->after('member_id')->index();
            $table->unsignedBigInteger('pacs_ai_calc_id')->nullable()->after('pacs_sid')->index();
        });
    }

    public function down(): void
    {
        Schema::table('image_gateway_ai_reports', function (Blueprint $table): void {
            $table->dropColumn(['pacs_sid', 'pacs_ai_calc_id']);
        });

        Schema::table('image_gateway_ai_jobs', function (Blueprint $table): void {
            $table->dropColumn(['pacs_sid', 'pacs_ai_calc_id']);
        });
    }
};
