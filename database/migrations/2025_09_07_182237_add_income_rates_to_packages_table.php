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
        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('direct_income_rate', 5, 2)->default(0.00)->after('level_unlock');
            $table->decimal('level_income_rate', 5, 2)->default(0.00)->after('direct_income_rate');
            $table->decimal('club_income_rate', 5, 2)->default(0.00)->after('level_income_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['direct_income_rate', 'level_income_rate', 'club_income_rate']);
        });
    }
};
