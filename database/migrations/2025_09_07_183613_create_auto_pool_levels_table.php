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
        Schema::create('auto_pool_levels', function (Blueprint $table) {
            $table->id();
            $table->integer('level')->unique(); // 4, 16, 64, 256, etc.
            $table->string('name'); // 4-Star Club, 16-Star Club, etc.
            $table->decimal('bonus_amount', 10, 2); // $0.5, $16, $64, etc.
            $table->integer('required_package_id'); // Package required for this level
            $table->integer('required_directs'); // Number of directs required
            $table->integer('required_group_size'); // Total group size (4, 16, 64, etc.)
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['level', 'is_active']);
            $table->index('required_package_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_pool_levels');
    }
};
