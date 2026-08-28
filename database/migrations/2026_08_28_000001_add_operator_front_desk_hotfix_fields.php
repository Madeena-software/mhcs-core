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
            $table->date('birth_date')->nullable()->change();
            $table->string('administrative_gender', 32)->nullable()->change();
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->boolean('operator_assisted_hotfix')->default(false)->index();
        });
    }

    public function down(): void
    {
        if (DB::table('members')->whereNull('birth_date')->orWhereNull('administrative_gender')->exists()) {
            throw new RuntimeException('Cannot reverse front-desk Member identity nullability while walk-in Members exist.');
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('operator_assisted_hotfix');
        });

        Schema::table('members', function (Blueprint $table): void {
            $table->date('birth_date')->nullable(false)->change();
            $table->string('administrative_gender', 32)->nullable(false)->change();
        });
    }
};
