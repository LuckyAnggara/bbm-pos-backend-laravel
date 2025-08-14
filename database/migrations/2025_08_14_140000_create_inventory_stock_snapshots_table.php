<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_stock_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->integer('year');
            $table->enum('type', ['closing', 'opening']);
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('cost_price', 18, 2)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->string('created_by_name')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'year', 'type']);
            $table->index(['year', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_snapshots');
    }
};
