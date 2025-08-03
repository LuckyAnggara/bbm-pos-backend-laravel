<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User; // Jangan lupa import model User

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Mencari user dengan email aa@gmail.com, jika tidak ada, baru dibuat.
        // Ini mencegah error duplikat jika seeder dijalankan berkali-kali.
        User::firstOrCreate(
            [
                'email' => 'aa@gmail.com'
            ],
            [
                'name' => 'Admin User',
                'password' => '123456', // Laravel akan otomatis hash password ini
                'role' => 'admin',
                // 'branch_id' => 1, // Mengasumsikan sudah ada branch dengan ID 1
            ]
        );
    }
}
