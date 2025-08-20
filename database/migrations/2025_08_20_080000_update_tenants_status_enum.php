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
        Schema::table('tenants', function (Blueprint $table) {
            // Update status enum to include trial and past_due
            $table->enum('status', ['active', 'trial', 'suspended', 'cancelled', 'past_due'])->default('trial')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('status', ['active', 'suspended', 'cancelled'])->default('active')->change();
        });
    }
};