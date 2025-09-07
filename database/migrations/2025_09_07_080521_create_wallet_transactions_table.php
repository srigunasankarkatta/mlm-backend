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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', [
                'credit',
                'debit',
                'transfer_in',
                'transfer_out',
                'withdrawal',
                'refund',
                'fee',
                'penalty'
            ]);
            $table->enum('category', [
                'direct_income',
                'level_income',
                'club_income',
                'auto_pool',
                'bonus',
                'package_purchase',
                'withdrawal',
                'transfer',
                'admin_credit',
                'admin_debit',
                'fee',
                'penalty'
            ]);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('reference_id')->nullable(); // External reference
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional transaction data
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('completed');
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'created_at']);
            $table->index(['wallet_id', 'type']);
            $table->index(['category', 'status']);
            $table->index('reference_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
