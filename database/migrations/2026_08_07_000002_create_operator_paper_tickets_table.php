<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_paper_tickets', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('booking_id', 36)->unique();
            $table->string('member_schedule_id', 36)->index();
            $table->string('operator_site_id', 36)->index();
            $table->string('operator_profile_id', 36)->index();
            $table->string('ticket_number', 32);
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $table->foreign('member_schedule_id')->references('id')->on('shift_schedules')->restrictOnDelete();
            $table->foreign('operator_site_id')->references('id')->on('operator_sites')->restrictOnDelete();
            $table->foreign('operator_profile_id')->references('id')->on('operator_profiles')->restrictOnDelete();
            $table->unique(['operator_site_id', 'member_schedule_id', 'ticket_number'], 'operator_paper_ticket_site_schedule_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_paper_tickets');
    }
};
