<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('stock_opname_sessions')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('product_name');
            $table->integer('system_quantity'); // recorded quantity before adjustment
            $table->integer('counted_quantity'); // physical count entered
            $table->integer('difference'); // counted - system
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
    }
};
