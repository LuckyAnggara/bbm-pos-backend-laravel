<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_opname_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['DRAFT', 'SUBMIT', 'APPROVED', 'REJECTED'])->default('DRAFT');
            $table->string('code')->unique();
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->unsignedInteger('total_items')->default(0);
            $table->integer('total_positive_adjustment')->default(0); // sum of increases
            $table->integer('total_negative_adjustment')->default(0); // sum of decreases (absolute value)
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_sessions');
    }
};
