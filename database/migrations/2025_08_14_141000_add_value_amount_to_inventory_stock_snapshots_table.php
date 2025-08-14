<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_stock_snapshots', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_stock_snapshots', 'value_amount')) {
                $table->decimal('value_amount', 18, 2)->nullable()->after('cost_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stock_snapshots', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_stock_snapshots', 'value_amount')) {
                $table->dropColumn('value_amount');
            }
        });
    }
};
