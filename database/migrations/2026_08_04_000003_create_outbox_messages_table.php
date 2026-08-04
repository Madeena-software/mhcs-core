<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->string('event_id', 191)->primary();
            $table->string('event_name', 191);
            $table->unsignedInteger('event_version');
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->string('subject_id', 191)->nullable();
            $table->string('correlation_id', 191)->nullable();
            $table->timestamp('available_at')->index();
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
