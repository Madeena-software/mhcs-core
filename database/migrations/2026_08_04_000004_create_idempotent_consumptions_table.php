<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotent_consumptions', function (Blueprint $table): void {
            $table->id();
            $table->string('message_id', 191);
            $table->string('consumer', 191);
            $table->string('payload_hash', 64);
            $table->string('status', 32)->default('pending')->index();
            $table->json('result')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error', 191)->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'consumer']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotent_consumptions');
    }
};
