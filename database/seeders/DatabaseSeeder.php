<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    // In api/database/seeders/DatabaseSeeder.php
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            BranchSeeder::class,     // Harus ada dulu
            CategorySeeder::class,   // Harus ada dulu
            SupplierSeeder::class,
            CustomerSeeder::class,
            // ProductSeeder::class,    // Baru bisa dijalankan
        ]);
    }
}
