<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('invoice_name');
            $table->string('printer_port')->nullable();
            $table->string('default_report_period')->default('monthly');
            $table->string('transaction_delete_password')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('currency')->default('IDR');
            $table->double('tax_rate')->default(11);
            $table->string('phone_number')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
