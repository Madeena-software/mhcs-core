<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_master_consents', function (Blueprint $table): void {
            $table->string('examination_consent_id', 36)->nullable()->after('member_id');
            $table->string('private_scan_object_key', 191)->nullable()->after('form_version');
            $table->string('private_scan_checksum', 64)->nullable()->after('private_scan_object_key');
            $table->unsignedBigInteger('private_scan_bytes')->nullable()->after('private_scan_checksum');
            $table->string('private_scan_format', 64)->nullable()->after('private_scan_bytes');

            $table->index('examination_consent_id');

            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('examination_consent_id')
                    ->references('id')
                    ->on('examination_consents')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('member_master_consents', function (Blueprint $table): void {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['examination_consent_id']);
            }
            $table->dropIndex(['examination_consent_id']);
            $table->dropColumn([
                'examination_consent_id',
                'private_scan_object_key',
                'private_scan_checksum',
                'private_scan_bytes',
                'private_scan_format',
            ]);
        });
    }
};
