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
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['earning', 'bonus', 'reward', 'holding', 'commission'])->default('earning');
            $table->decimal('balance', 15, 2)->default(0.00);
            $table->decimal('pending_balance', 15, 2)->default(0.00);
            $table->decimal('withdrawn_balance', 15, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable(); // Wallet-specific settings
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'type']);
            $table->unique(['user_id', 'type']); // One wallet per type per user
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
