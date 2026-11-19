<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Only the administrator is seeded. Users register themselves via the API.
     */
    public function run(): void
    {
        $this->call(AdminSeeder::class);
    }
}
