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
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('daily_meal_allowance', 15, 2)->default(0)->after('daily_salary');
            $table->decimal('monthly_meal_allowance', 15, 2)->default(0)->after('daily_meal_allowance');
            $table->decimal('bonus', 15, 2)->default(0)->after('monthly_meal_allowance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['daily_meal_allowance', 'monthly_meal_allowance', 'bonus']);
        });
    }
};
