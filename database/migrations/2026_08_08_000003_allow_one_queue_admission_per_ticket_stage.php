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
            $table->dropUnique('operator_queue_admissions_operator_paper_ticket_id_unique');
            $table->unique(
                ['operator_paper_ticket_id', 'stage'],
                'operator_queue_admission_ticket_stage_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('operator_queue_admissions', function (Blueprint $table): void {
            $table->dropUnique('operator_queue_admission_ticket_stage_unique');
            $table->unique('operator_paper_ticket_id');
        });
    }
};
