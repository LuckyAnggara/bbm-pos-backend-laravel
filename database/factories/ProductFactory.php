<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Mengambil branch dan category secara acak dari yang sudah ada di database.
        // Ini mengharuskan BranchSeeder dan CategorySeeder sudah dijalankan terlebih dahulu.
        $branch = Branch::inRandomOrder()->first();
        $category = Category::inRandomOrder()->first();

        $costPrice = $this->faker->numberBetween(5000, 100000); // Harga beli antara 5rb - 100rb
        $price = $costPrice * $this->faker->randomFloat(2, 1.25, 2.0); // Margin keuntungan 25% - 100%

        return [
            'name' => $this->faker->unique()->words(3, true), // Contoh: 'Enim Quis Animi'
            'sku' => 'SKU-'.$this->faker->unique()->randomNumber(6),
            'quantity' => $this->faker->numberBetween(10, 250),
            'cost_price' => $costPrice,
            'price' => round($price / 500) * 500, // Bulatkan harga jual ke 500 perak terdekat
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'category_name' => $category->name, // Mengisi nama kategori secara otomatis
            'image_url' => null,
            'image_hint' => null,
        ];
    }
}
