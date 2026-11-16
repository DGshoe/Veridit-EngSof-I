<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

/**
 * D2 catalog from config/commerce.php. Idempotent: safe to run on every deploy
 * (updates name/credits/price; existing orders keep their own snapshot).
 */
class PackageSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('commerce.packages') as $code => $package) {
            Package::query()->updateOrCreate(['code' => $code], [
                'name' => $package['name'],
                'credits' => $package['credits'],
                'price_cents' => $package['price_cents'],
                'active' => true,
            ]);
        }
    }
}
