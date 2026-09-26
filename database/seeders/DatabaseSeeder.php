<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Essential data only (notification templates, draft content pages).
     * Demo data is separate: php artisan db:seed --class=DemoSeeder  (refused in production)
     */
    public function run(): void
    {
        $this->call(EssentialSeeder::class);
    }
}
