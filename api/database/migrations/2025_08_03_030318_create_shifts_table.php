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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('status')->nullable();
            $table->dateTime('start_shift')->nullable();
            $table->decimal('starting_balance', 15, 2)->default(0);
            $table->decimal('total_sales', 15, 2)->default(0);
            $table->decimal('total_cash_payments', 15, 2)->default(0);
            $table->decimal('total_other_payments', 15, 2)->default(0);
            $table->decimal('total_bank_payments', 15, 2)->default(0);
            $table->decimal('total_credit_payments', 15, 2)->default(0);
            $table->decimal('total_card_payments', 15, 2)->default(0);
            $table->decimal('total_qris_payments', 15, 2)->default(0);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('user_name')->nullable();
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->dateTime('end_shift')->nullable();
            $table->timestamps();

            // Foreign keys (optional, uncomment if needed)
            // $table->foreign('branchId')->references('id')->on('branches');
            // $table->foreign('userId')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
