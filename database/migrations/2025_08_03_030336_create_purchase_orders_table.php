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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            // Sesuai gambar: poNumber
            $table->string('po_number')->unique()->comment('Nomor Purchase Order');

            // Sesuai gambar: branchId
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->onDelete('cascade');

            // Sesuai gambar: supplierId & supplierName
            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->onDelete('restrict');
            $table->string('supplier_name');

            // Sesuai gambar: orderDate, expectedDeliveryDate, paymentDueDateOnPO
            $table->dateTime('order_date');
            $table->dateTime('expected_delivery_date')->nullable();
            $table->dateTime('payment_due_date')->nullable();

            // Sesuai gambar: notes
            $table->text('notes')->nullable();

            // Sesuai gambar: createdById
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null')
                ->comment('User yang membuat PO');

            // Sesuai gambar: isCreditPurchase
            $table->boolean('is_credit')->default(false);

            // Sesuai gambar: paymentTermsOnPO
            $table->string('payment_terms')->nullable();

            // Sesuai gambar: supplierInvoiceNumber
            $table->string('supplier_invoice_number')->nullable();

            // Sesuai gambar: paymentStatusOnPO & status
            $table->string('payment_status')->default('unpaid');
            $table->string('status')->default('pending');

            // Sesuai gambar: subtotal, taxDiscountAmount, shippingCostCharged, otherCosts, totalAmount
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_discount_amount', 15, 2)->default(0);
            $table->decimal('shipping_cost_charged', 15, 2)->default(0);
            $table->decimal('other_costs', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);

            // Sesuai gambar: outstandingPOAmount
            $table->decimal('outstanding_amount', 15, 2)->default(0);

            // Sesuai gambar: createdAt, updatedAt
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
