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
        Schema::create('group_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('auto_pool_level'); // 4, 16, 64, etc.
            $table->integer('group_size'); // Actual group size achieved
            $table->integer('directs_count'); // Number of directs user had
            $table->integer('total_network_size'); // Total network size
            $table->decimal('bonus_amount', 10, 2); // Bonus amount earned
            $table->boolean('bonus_paid')->default(false);
            $table->timestamp('completed_at');
            $table->json('completion_details')->nullable(); // Store detailed completion info
            $table->timestamps();

            $table->index(['user_id', 'auto_pool_level']);
            $table->index(['auto_pool_level', 'completed_at']);
            $table->index('bonus_paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_completions');
    }
};
