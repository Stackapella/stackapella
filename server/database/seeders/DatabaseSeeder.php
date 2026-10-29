<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * php artisan db:seed: the demo data, and only on a laptop. Production is never seeded.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
