<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per user: current balance (credits never expire).
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('user_id')->primary();     // Identity "sub" — no FK (ADR-012)
            $table->integer('balance')->default(0);
            $table->timestamps();
        });

        // Immutable ledger: every balance change has exactly one entry.
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('type', 10);              // credito | debito
            $table->unsignedInteger('amount');
            $table->integer('balance_after');
            $table->string('reason', 30);            // compra | captura_imagem | captura_video
            $table->string('reference_type', 30);    // order | capture
            $table->string('reference_id', 100);
            $table->timestamp('created_at')->useCurrent();

            // Idempotency (ADR-002/011): one credit per order, one debit per capture.
            $table->unique(['reference_type', 'reference_id', 'type']);
            $table->index(['user_id', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE wallets ADD CONSTRAINT wallets_balance_not_negative CHECK (balance >= 0)');
            DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT ledger_amount_positive CHECK (amount > 0)');
            DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT ledger_balance_not_negative CHECK (balance_after >= 0)');
            DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_type_check CHECK (type IN ('credito','debito'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('wallets');
    }
};
