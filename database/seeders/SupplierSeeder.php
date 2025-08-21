<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::firstOrCreate(
            ['email' => 'contact@indofood.co.id'],
            [
                'branch_id' => 1,
                'name' => 'PT Indofood Sukses Makmur Tbk',
                'contact_person' => 'Bapak Budi',
                'phone' => '021-555123',
                'address' => 'Sudirman Plaza, Jakarta',
            ]
        );
    }
}
