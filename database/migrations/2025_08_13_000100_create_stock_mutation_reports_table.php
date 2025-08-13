<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_mutation_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->json('data');
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();

            $table->unique(['branch_id', 'start_date', 'end_date'], 'uniq_branch_period');
            $table->index(['branch_id', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_mutation_reports');
    }
};
