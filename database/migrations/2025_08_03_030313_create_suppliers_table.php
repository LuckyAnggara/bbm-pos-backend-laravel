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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            // Sesuai gambar: name
            $table->string('name');

            // Sesuai gambar: name
            $table->string('contact_person')->nullable();
            // Sesuai gambar: email
            $table->string('email')->unique()->nullable();
            // Sesuai gambar: phone
            $table->string('phone')->nullable();

            // Sesuai gambar: address
            $table->text('address')->nullable();

            // Sesuai gambar: notes
            $table->text('notes')->nullable();

            // Sesuai gambar: branchId
            // Supplier ini spesifik untuk cabang mana
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->onDelete('cascade');

            // Sesuai gambar: createdAt, updatedAt
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
