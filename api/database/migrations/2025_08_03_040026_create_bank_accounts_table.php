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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('set null');
            $table->string('bank_name'); // Nama produk
            $table->string('account_number'); // Nama produk
            $table->string('account_holder_number'); // Nama produk
            $table->boolean('is_active')->default(false); // Nama produk
            $table->boolean('is_default'); // Nama produk
            $table->timestamps(); // created_at dan updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
