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
        Schema::table('operator_identity_verifications', function (Blueprint $table): void {
            $table->string('active_claim_operator_profile_id', 36)
                ->nullable()
                ->unique('operator_identity_active_claim_unique')
                ->after('operator_profile_id');
            $table->foreign('active_claim_operator_profile_id', 'operator_identity_active_claim_profile_fk')
                ->references('id')
                ->on('operator_profiles')
                ->restrictOnDelete();
        });

        foreach (DB::table('operator_identity_verifications')->where('state', 'open')->orderBy('id')->get() as $case) {
            DB::table('operator_identity_verifications')->where('id', $case->id)->update([
                'active_claim_operator_profile_id' => $case->operator_profile_id,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('operator_identity_verifications', function (Blueprint $table): void {
            $table->dropForeign('operator_identity_active_claim_profile_fk');
            $table->dropUnique('operator_identity_active_claim_unique');
            $table->dropColumn('active_claim_operator_profile_id');
        });
    }
};
