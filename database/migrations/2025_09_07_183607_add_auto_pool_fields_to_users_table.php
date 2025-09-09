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
            $table->integer('auto_pool_level')->default(0)->after('package_id');
            $table->integer('group_completion_count')->default(0)->after('auto_pool_level');
            $table->timestamp('last_group_completion_at')->nullable()->after('group_completion_count');
            $table->decimal('total_auto_pool_earnings', 10, 2)->default(0.00)->after('last_group_completion_at');
            $table->json('auto_pool_stats')->nullable()->after('total_auto_pool_earnings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'auto_pool_level',
                'group_completion_count',
                'last_group_completion_at',
                'total_auto_pool_earnings',
                'auto_pool_stats'
            ]);
        });
    }
};
