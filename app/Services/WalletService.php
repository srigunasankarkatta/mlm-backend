<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletService
{
    /**
     * Create default wallets for a user
     */
    public function createDefaultWallets(User $user): array
    {
        $wallets = [];
        $walletTypes = [
            Wallet::TYPE_EARNING,
            Wallet::TYPE_BONUS,
            Wallet::TYPE_REWARD,
            Wallet::TYPE_HOLDING,
            Wallet::TYPE_COMMISSION
        ];

        foreach ($walletTypes as $type) {
            $wallet = Wallet::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => $type
                ],
                [
                    'balance' => 0.00,
                    'pending_balance' => 0.00,
                    'withdrawn_balance' => 0.00,
                    'is_active' => true,
                    'settings' => $this->getDefaultWalletSettings($type)
                ]
            );
            $wallets[] = $wallet;
        }

        return $wallets;
    }

    /**
     * Get default settings for wallet type
     */
    private function getDefaultWalletSettings(string $type): array
    {
        $settings = [
            'withdrawal_enabled' => true,
            'min_withdrawal' => 10.00,
            'max_withdrawal' => 5000.00,
            'daily_limit' => 500.00,
            'monthly_limit' => 5000.00
        ];

        switch ($type) {
            case Wallet::TYPE_EARNING:
                $settings['withdrawal_enabled'] = true;
                break;
            case Wallet::TYPE_BONUS:
                $settings['withdrawal_enabled'] = true;
                $settings['min_withdrawal'] = 50.00;
                break;
            case Wallet::TYPE_REWARD:
                $settings['withdrawal_enabled'] = false;
                break;
            case Wallet::TYPE_HOLDING:
                $settings['withdrawal_enabled'] = false;
                break;
            case Wallet::TYPE_COMMISSION:
                $settings['withdrawal_enabled'] = true;
                break;
        }

        return $settings;
    }

    /**
     * Credit amount to wallet
     */
    public function credit(int $userId, string $walletType, float $amount, string $category, string $description = null, array $metadata = []): WalletTransaction
    {
        return DB::transaction(function () use ($userId, $walletType, $amount, $category, $description, $metadata) {
            $wallet = $this->getOrCreateWallet($userId, $walletType);

            $balanceBefore = $wallet->balance;
            $balanceAfter = $balanceBefore + $amount;

            // Update wallet balance
            $wallet->update(['balance' => $balanceAfter]);

            // Create transaction record
            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'type' => WalletTransaction::TYPE_CREDIT,
                'category' => $category,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_id' => $this->generateReferenceId(),
                'description' => $description,
                'metadata' => $metadata,
                'status' => WalletTransaction::STATUS_COMPLETED
            ]);

            return $transaction;
        });
    }

    /**
     * Debit amount from wallet
     */
    public function debit(int $userId, string $walletType, float $amount, string $category, string $description = null, array $metadata = []): WalletTransaction
    {
        return DB::transaction(function () use ($userId, $walletType, $amount, $category, $description, $metadata) {
            $wallet = $this->getOrCreateWallet($userId, $walletType);

            if ($wallet->available_balance < $amount) {
                throw new \Exception('Insufficient wallet balance');
            }

            $balanceBefore = $wallet->balance;
            $balanceAfter = $balanceBefore - $amount;

            // Update wallet balance
            $wallet->update(['balance' => $balanceAfter]);

            // Create transaction record
            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'type' => WalletTransaction::TYPE_DEBIT,
                'category' => $category,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_id' => $this->generateReferenceId(),
                'description' => $description,
                'metadata' => $metadata,
                'status' => WalletTransaction::STATUS_COMPLETED
            ]);

            return $transaction;
        });
    }

    /**
     * Transfer between wallets
     */
    public function transfer(int $userId, string $fromWalletType, string $toWalletType, float $amount, string $description = null): array
    {
        return DB::transaction(function () use ($userId, $fromWalletType, $toWalletType, $amount, $description) {
            // Debit from source wallet
            $debitTransaction = $this->debit(
                $userId,
                $fromWalletType,
                $amount,
                WalletTransaction::CATEGORY_TRANSFER,
                $description ?: "Transfer to {$toWalletType} wallet"
            );

            // Credit to destination wallet
            $creditTransaction = $this->credit(
                $userId,
                $toWalletType,
                $amount,
                WalletTransaction::CATEGORY_TRANSFER,
                $description ?: "Transfer from {$fromWalletType} wallet"
            );

            return [
                'debit_transaction' => $debitTransaction,
                'credit_transaction' => $creditTransaction
            ];
        });
    }

    /**
     * Create withdrawal request
     */
    public function createWithdrawal(int $userId, string $walletType, float $amount, string $method, array $paymentDetails, string $notes = null): Withdrawal
    {
        return DB::transaction(function () use ($userId, $walletType, $amount, $method, $paymentDetails, $notes) {
            $wallet = $this->getOrCreateWallet($userId, $walletType);

            // Check withdrawal limits
            $this->validateWithdrawal($wallet, $amount);

            // Calculate fees
            $fee = $this->calculateWithdrawalFee($amount, $method);
            $netAmount = $amount - $fee;

            // Create withdrawal record
            $withdrawal = Withdrawal::create([
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'withdrawal_id' => $this->generateWithdrawalId(),
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'method' => $method,
                'payment_details' => $paymentDetails,
                'status' => Withdrawal::STATUS_PENDING,
                'user_notes' => $notes,
                'metadata' => [
                    'requested_at' => now()->toISOString(),
                    'wallet_type' => $walletType
                ]
            ]);

            // Hold the amount in pending balance
            $wallet->increment('pending_balance', $amount);
            $wallet->decrement('balance', $amount);

            return $withdrawal;
        });
    }

    /**
     * Process withdrawal (approve/reject)
     */
    public function processWithdrawal(int $withdrawalId, string $status, int $processedBy, string $adminNotes = null): Withdrawal
    {
        return DB::transaction(function () use ($withdrawalId, $status, $processedBy, $adminNotes) {
            $withdrawal = Withdrawal::findOrFail($withdrawalId);
            $wallet = $withdrawal->wallet;

            $oldStatus = $withdrawal->status;
            $withdrawal->update([
                'status' => $status,
                'admin_notes' => $adminNotes,
                'processed_by' => $processedBy,
                'processed_at' => now()
            ]);

            if ($status === Withdrawal::STATUS_REJECTED || $status === Withdrawal::STATUS_CANCELLED) {
                // Return funds to available balance
                $wallet->decrement('pending_balance', $withdrawal->amount);
                $wallet->increment('balance', $withdrawal->amount);
            } elseif ($status === Withdrawal::STATUS_COMPLETED) {
                // Move from pending to withdrawn
                $wallet->decrement('pending_balance', $withdrawal->amount);
                $wallet->increment('withdrawn_balance', $withdrawal->amount);
            }

            return $withdrawal;
        });
    }

    /**
     * Get or create wallet for user
     */
    public function getOrCreateWallet(int $userId, string $walletType): Wallet
    {
        $wallet = Wallet::where('user_id', $userId)
            ->where('type', $walletType)
            ->first();

        if (!$wallet) {
            $user = User::findOrFail($userId);
            $wallets = $this->createDefaultWallets($user);
            $wallet = collect($wallets)->firstWhere('type', $walletType);
        }

        return $wallet;
    }

    /**
     * Get user's wallet balances
     */
    public function getUserWalletBalances(int $userId): array
    {
        $wallets = Wallet::where('user_id', $userId)->get();

        $balances = [];
        foreach ($wallets as $wallet) {
            $balances[$wallet->type] = [
                'balance' => $wallet->balance,
                'pending_balance' => $wallet->pending_balance,
                'withdrawn_balance' => $wallet->withdrawn_balance,
                'available_balance' => $wallet->available_balance,
                'total_balance' => $wallet->total_balance,
                'is_active' => $wallet->is_active
            ];
        }

        return $balances;
    }

    /**
     * Validate withdrawal request
     */
    private function validateWithdrawal(Wallet $wallet, float $amount): void
    {
        $settings = $wallet->settings ?? [];

        if (!$settings['withdrawal_enabled'] ?? true) {
            throw new \Exception('Withdrawals are not enabled for this wallet type');
        }

        if ($amount < ($settings['min_withdrawal'] ?? 10.00)) {
            throw new \Exception('Amount is below minimum withdrawal limit');
        }

        if ($amount > ($settings['max_withdrawal'] ?? 5000.00)) {
            throw new \Exception('Amount exceeds maximum withdrawal limit');
        }

        if ($wallet->available_balance < $amount) {
            throw new \Exception('Insufficient wallet balance');
        }
    }

    /**
     * Calculate withdrawal fee
     */
    private function calculateWithdrawalFee(float $amount, string $method): float
    {
        $feePercentage = 0.02; // 2% default fee

        switch ($method) {
            case Withdrawal::METHOD_BANK_TRANSFER:
                $feePercentage = 0.02; // 2%
                break;
            case Withdrawal::METHOD_DIGITAL_WALLET:
                $feePercentage = 0.03; // 3%
                break;
            case Withdrawal::METHOD_CRYPTOCURRENCY:
                $feePercentage = 0.05; // 5%
                break;
            case Withdrawal::METHOD_CHECK:
                $feePercentage = 0.01; // 1%
                break;
            case Withdrawal::METHOD_CASH_PICKUP:
                $feePercentage = 0.04; // 4%
                break;
        }

        return round($amount * $feePercentage, 2);
    }

    /**
     * Generate unique reference ID
     */
    private function generateReferenceId(): string
    {
        return 'WTX-' . strtoupper(Str::random(10));
    }

    /**
     * Generate unique withdrawal ID
     */
    private function generateWithdrawalId(): string
    {
        return 'WTH-' . strtoupper(Str::random(10));
    }
}


