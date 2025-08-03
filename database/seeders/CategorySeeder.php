<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        Category::firstOrCreate(['name' => 'Makanan Ringan'], ['branch_id' => 1, 'description' => 'Berbagai macam snack dan camilan.']);
        Category::firstOrCreate(['name' => 'Minuman Dingin'], ['branch_id' => 1, 'description' => 'Minuman soda, jus, dan air mineral.']);
        Category::firstOrCreate(['name' => 'Kopi & Teh'], ['branch_id' => 1, 'description' => 'Berbagai jenis kopi dan teh sachet.']);
    }
}
