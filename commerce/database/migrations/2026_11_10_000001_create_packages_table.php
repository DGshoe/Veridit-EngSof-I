<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();   // basico | medio | premium (D2)
            $table->string('name', 50);
            $table->unsignedInteger('credits');
            $table->unsignedInteger('price_cents');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE packages ADD CONSTRAINT packages_credits_positive CHECK (credits > 0)');
            DB::statement('ALTER TABLE packages ADD CONSTRAINT packages_price_positive CHECK (price_cents > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
