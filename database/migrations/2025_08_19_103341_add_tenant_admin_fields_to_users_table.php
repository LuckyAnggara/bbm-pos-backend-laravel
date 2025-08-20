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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->after('branch_id')->nullable()->constrained('tenants')->onDelete('cascade');
            $table->enum('user_type', ['super_admin', 'tenant_admin', 'branch_user'])->after('role')->default('branch_user');
            $table->boolean('is_tenant_owner')->after('user_type')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['tenant_id', 'user_type', 'is_tenant_owner']);
        });
    }
};
