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
        Schema::table('suppliers', function (Blueprint $table) {
            // Company information
            $table->enum('company_type', ['individual', 'company'])->nullable()->after('notes');
            $table->string('tax_id')->nullable()->after('company_type');
            $table->string('industry')->nullable()->after('tax_id');
            $table->string('website')->nullable()->after('industry');
            $table->integer('rating')->unsigned()->nullable()->after('website')->comment('Rating 1-5');

            // Financial information
            $table->decimal('credit_limit', 15, 2)->nullable()->after('rating');
            $table->string('payment_terms')->nullable()->after('credit_limit');

            // Bank information
            $table->string('bank_name')->nullable()->after('payment_terms');
            $table->string('bank_account_number')->nullable()->after('bank_name');
            $table->string('bank_account_name')->nullable()->after('bank_account_number');

            // Status
            $table->boolean('is_active')->default(true)->after('bank_account_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'company_type',
                'tax_id',
                'industry',
                'website',
                'rating',
                'credit_limit',
                'payment_terms',
                'bank_name',
                'bank_account_number',
                'bank_account_name',
                'is_active',
            ]);
        });
    }
};
