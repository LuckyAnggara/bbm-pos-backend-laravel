<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('report_type'); // sales_summary | income_statement | balance_sheet
            $table->date('start_date');
            $table->date('end_date');
            $table->json('data');
            $table->timestamps();
            $table->unique(['branch_id', 'report_type', 'start_date', 'end_date'], 'uniq_report_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_reports');
    }
};
