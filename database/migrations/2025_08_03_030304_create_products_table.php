<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('restrict');
            $table->string('category_name'); // Nama produk
            $table->string('name'); // Nama produk
            $table->string('sku')->unique()->nullable(); // Kode produk/SKU, harus unik
            $table->integer('quantity')->default(0); // Stok produk
            $table->decimal('cost_price', 12, 2); // Harga beli
            $table->decimal('price', 12, 2); // Harga jual
            $table->string('image_url')->nullable(); // Path ke gambar produk (opsional)
            $table->string('image_hint')->nullable(); // Path ke gambar produk (opsional)
            $table->timestamps(); // created_at dan updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
