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
        Schema::table('payrolls', function (Blueprint $table) {
            // Drop employee_id karena sekarang menggunakan payroll_details
            $table->dropForeign(['employee_id']);
            $table->dropColumn([
                'employee_id',
                'days_worked',
                'base_salary',
                'overtime_amount',
                'bonus_amount',
                'deduction_amount'
            ]);

            // Add new fields for batch payroll
            $table->string('title')->after('payroll_code');
            $table->text('description')->nullable()->after('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            // Restore the old structure
            $table->unsignedBigInteger('employee_id')->after('branch_id');
            $table->integer('days_worked')->default(0)->after('period_end');
            $table->decimal('base_salary', 15, 2)->after('days_worked');
            $table->decimal('overtime_amount', 15, 2)->default(0)->after('base_salary');
            $table->decimal('bonus_amount', 15, 2)->default(0)->after('overtime_amount');
            $table->decimal('deduction_amount', 15, 2)->default(0)->after('bonus_amount');

            $table->dropColumn(['title', 'description']);

            $table->foreign('employee_id')->references('id')->on('employees');
        });
    }
};
