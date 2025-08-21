<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('purchase_orders', 'tax_amount')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_discount_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_orders', 'tax_amount')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('tax_amount');
            });
        }
    }
};
