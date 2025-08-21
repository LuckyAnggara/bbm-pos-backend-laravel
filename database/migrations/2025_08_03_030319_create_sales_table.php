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
        Schema::create('sales', function (Blueprint $table) {

            $table->id();
            $table->string('transaction_number');
            $table->string('notes')->nullable();
            $table->string('status');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('set null');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->string('user_name');
            $table->string('customer_name')->nullable();
            $table->double('subtotal', 15, 2);
            $table->double('total_discount_amount', 15, 2);
            $table->double('tax_amount', 15, 2);
            $table->double('shipping_cost', 15, 2)->default(0);
            $table->double('total_amount', 15, 2);
            $table->double('total_cogs', 15, 2)->default(0);
            $table->string('payment_method');
            $table->string('payment_status');
            $table->double('amount_paid', 15, 2);
            $table->double('change_given', 15, 2)->default(0);
            $table->double('items_discount_amount', 15, 2)->default(0);
            $table->string('voucher_code')->nullable();
            $table->double('voucher_discount_amount', 15, 2)->default(0);
            $table->boolean('is_credit_sale')->default(false);
            $table->dateTime('credit_due_date')->nullable();
            $table->double('outstanding_amount', 15, 2)->default(0);
            $table->string('bank_transaction_ref')->nullable();
            $table->string('bank_name')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->string('returned_reason')->nullable();
            $table->string('returned_by_user_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
