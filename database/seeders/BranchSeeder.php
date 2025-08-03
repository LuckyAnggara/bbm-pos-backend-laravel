<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::firstOrCreate(
            ['name' => 'Toko Pusat'],
            [
                'invoice_name' => 'Toko Pusat Utama',
                'address' => 'Jl. Jenderal Sudirman No. 1, Jakarta',
                'phone' => '021-123456',
                'tax_rate' => 11,
            ]
        );

        Branch::firstOrCreate(
            ['name' => 'Cabang Bandung'],
            [
                'invoice_name' => 'Toko Cabang Bandung',
                'address' => 'Jl. Asia Afrika No. 101, Bandung',
                'phone' => '022-654321',
                'tax_rate' => 11,
            ]
        );
    }
}
