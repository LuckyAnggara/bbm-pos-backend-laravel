<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // Membuat customer default untuk 'Walk-in Customer'
        Customer::firstOrCreate(
            ['name' => 'Walk-in Customer'],
            [
                'branch_id' => 1,
                'email' => 'walkin@example.com',
                'phone' => '0000',
                'address' => 'N/A',
                'notes' => 'Pelanggan umum tanpa data spesifik.',
            ]
        );

        Customer::firstOrCreate(
            ['email' => 'andisusanto@gmail.com'],
            [
                'branch_id' => 1,
                'name' => 'Andi Susanto',
                'phone' => '081234567890',
                'address' => 'Jl. Merdeka No. 5',
            ]
        );
    }
}
