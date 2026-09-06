<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('image_gateway_ai_reports', function (Blueprint $table): void {
            $table->timestamp('derived_at')->nullable()->after('derived_filename');
            $table->string('derived_error_code', 64)->nullable()->after('derived_at');
        });
    }

    public function down(): void
    {
        Schema::table('image_gateway_ai_reports', function (Blueprint $table): void {
            $table->dropColumn(['derived_at', 'derived_error_code']);
        });
    }
};
