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
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();

            // Sesuai gambar: poId -> Relasi ke purchase_orders
            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders')
                ->onDelete('cascade');

            // Sesuai gambar: branchId -> Relasi ke branches
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->onDelete('cascade');

            // Sesuai gambar: supplierId -> Relasi ke supplier
            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->onDelete('restrict');

            $table->dateTime('payment_date')->nullable();
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('payment_method');
            $table->string('recorded_by_user_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
