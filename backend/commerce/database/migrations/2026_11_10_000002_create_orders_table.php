<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();          // Identity "sub" — no FK (other database, ADR-012)
            $table->foreignUuid('package_id')->constrained('packages');

            // Snapshot of the package at purchase time (prices may change later)
            $table->string('package_code', 20);
            $table->unsignedInteger('credits');
            $table->unsignedInteger('amount_cents');

            $table->string('status', 20)->default('pendente');
            $table->string('payment_method', 20);
            $table->string('provider_payment_id', 100)->nullable();
            $table->json('payment_data')->nullable();   // e.g. PIX QR code, checkout URL

            $table->string('idempotency_key', 100)->nullable();

            // Billing (T05): only data already in Identity; CPF stays masked (ADR-012)
            $table->string('billing_name', 150);
            $table->string('billing_email', 150);
            $table->string('billing_cpf_masked', 14)->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
            $table->unique(['payment_method', 'provider_payment_id']);
            $table->index(['user_id', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('pendente','pago','falhou'))");
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_amount_positive CHECK (amount_cents > 0 AND credits > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
