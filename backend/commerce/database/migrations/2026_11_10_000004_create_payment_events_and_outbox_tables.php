<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Inbox of provider events (ADR-011): the same event delivered N times is processed once.
        Schema::create('payment_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 20);
            $table->string('event_id', 150);
            $table->string('provider_payment_id', 100)->nullable();
            $table->string('status', 20);                 // aprovado | recusado | pendente (normalized)
            $table->string('result', 20)->nullable();     // aplicado | ignorado
            $table->foreignUuid('order_id')->nullable()->constrained('orders');
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['provider', 'event_id']);
        });

        // Transactional outbox (ADR-011): written in the same transaction as the effect.
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 60);
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error', 255)->nullable();

            $table->index(['published_at', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
        Schema::dropIfExists('payment_events');
    }
};
