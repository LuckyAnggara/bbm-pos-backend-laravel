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
        Schema::table('customers', function (Blueprint $table) {
            // Customer Classification
            $table->enum('customer_type', ['individual', 'business'])->default('individual')->after('notes');
            $table->enum('customer_tier', ['regular', 'silver', 'gold', 'platinum'])->default('regular')->after('customer_type');

            // Business-specific fields
            $table->string('company_name')->nullable()->after('customer_tier');
            $table->string('tax_id')->nullable()->after('company_name'); // NPWP for Indonesia
            $table->string('business_type')->nullable()->after('tax_id');

            // Credit Management (for business customers)
            $table->decimal('credit_limit', 15, 2)->default(0)->after('business_type');
            $table->integer('payment_terms_days')->default(0)->after('credit_limit'); // 0=cash, 30=NET30, etc
            $table->enum('credit_status', ['active', 'suspended', 'blocked'])->default('active')->after('payment_terms_days');

            // Loyalty & Analytics
            $table->decimal('loyalty_points', 10, 2)->default(0)->after('credit_status');
            $table->decimal('total_spent', 15, 2)->default(0)->after('loyalty_points');
            $table->integer('total_transactions')->default(0)->after('total_spent');
            $table->timestamp('last_purchase_date')->nullable()->after('total_transactions');

            // Preferences
            $table->json('preferences')->nullable()->after('last_purchase_date'); // Store pricing preferences, communication preferences, etc.
            $table->boolean('is_active')->default(true)->after('preferences');

            // Add indexes for performance
            $table->index(['customer_type', 'customer_tier']);
            $table->index(['credit_status']);
            $table->index(['is_active']);
            $table->index(['last_purchase_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'customer_type',
                'customer_tier',
                'company_name',
                'tax_id',
                'business_type',
                'credit_limit',
                'payment_terms_days',
                'credit_status',
                'loyalty_points',
                'total_spent',
                'total_transactions',
                'last_purchase_date',
                'preferences',
                'is_active'
            ]);
        });
    }
};
