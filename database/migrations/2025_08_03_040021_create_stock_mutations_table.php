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
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->id();

            // Sesuai gambar: branchId
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->onDelete('cascade');

            // Sesuai gambar: itemId -> diubah menjadi product_id
            $table->foreignId('product_id')
                ->constrained('products')
                ->onDelete('cascade');

            // Sesuai gambar: itemName
            $table->string('product_name');

            // Sesuai gambar: change -> diubah menjadi quantity_change
            $table->integer('quantity_change')->comment('Positif untuk stok masuk, negatif untuk keluar');

            // Sesuai gambar: previousQuantity -> diubah menjadi stock_before
            $table->integer('stock_before');

            // Sesuai gambar: newQuantity -> diubah menjadi stock_after
            $table->integer('stock_after');

            // Sesuai gambar: type
            $table->string('type')->comment('Contoh: sale, purchase, adjustment, transfer');

            // Sesuai gambar: description
            $table->string('description')->nullable();

            // Sesuai gambar: relatedTransactionId -> diubah menjadi reference (Polymorphic)
            // Ini cara Laravel untuk merujuk ke berbagai jenis transaksi (Sale, PurchaseOrder, dll)
            $table->nullableMorphs('reference');

            // Sesuai gambar: userId & userName
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->string('user_name');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_mutations');
    }
};
