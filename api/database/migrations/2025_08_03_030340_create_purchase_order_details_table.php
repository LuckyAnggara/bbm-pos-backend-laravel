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
        Schema::create('purchase_order_details', function (Blueprint $table) {
            $table->id();

            // Sesuai gambar: poId -> Relasi ke purchase_orders
            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders')
                ->onDelete('cascade');

            // Sesuai gambar: branchId -> Relasi ke branches
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->onDelete('cascade');

            // Sesuai gambar: productId -> Relasi ke products
            $table->foreignId('product_id')
                ->constrained('products')
                ->onDelete('restrict');

            // Sesuai gambar: productName
            $table->string('product_name');

            // Sesuai gambar: orderedQuantity
            $table->integer('ordered_quantity');

            // Sesuai gambar: receivedQuantity
            $table->integer('received_quantity')->nullable()->default(0);
            $table->dateTime('received_date')->nullable();
            // Sesuai gambar: purchasePrice
            $table->decimal('purchase_price', 15, 2);

            // Sesuai gambar: totalPrice
            $table->decimal('total_price', 15, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_details');
    }
};
