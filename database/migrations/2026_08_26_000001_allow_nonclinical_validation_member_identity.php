<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->string('identity_document_type', 16)->nullable()->change();
            $table->text('encrypted_nik')->nullable()->change();
            $table->string('nik_lookup_digest', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('members')->where(function ($query): void {
            $query->whereNull('identity_document_type')
                ->orWhereNull('encrypted_nik')
                ->orWhereNull('nik_lookup_digest');
        })->exists()) {
            throw new RuntimeException('Cannot reverse nonclinical identity nullability while validation Members exist.');
        }

        Schema::table('members', function (Blueprint $table): void {
            $table->string('identity_document_type', 16)->nullable(false)->change();
            $table->text('encrypted_nik')->nullable(false)->change();
            $table->string('nik_lookup_digest', 64)->nullable(false)->change();
        });
    }
};
