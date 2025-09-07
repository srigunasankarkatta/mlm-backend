<?php

namespace App\Services\Admin;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;

class AdminWalletService
{
    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Get all wallets with user information
     */
    public function getAllWallets(array $filters = []): array
    {
        $query = Wallet::with(['user']);

        // Apply filters
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $filters['per_page'] ?? 15;
        $wallets = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $formattedWallets = $wallets->getCollection()->map(function ($wallet) {
            return $this->formatWalletForAdmin($wallet);
        });

        return [
            'wallets' => $formattedWallets,
            'pagination' => [
                'current_page' => $wallets->currentPage(),
                'last_page' => $wallets->lastPage(),
                'per_page' => $wallets->perPage(),
                'total' => $wallets->total(),
                'from' => $wallets->firstItem(),
                'to' => $wallets->lastItem(),
                'has_more_pages' => $wallets->hasMorePages()
            ]
        ];
    }

    /**
     * Get all wallet transactions with user information
     */
    public function getAllWalletTransactions(array $filters = []): array
    {
        $query = WalletTransaction::with(['user', 'wallet']);

        // Apply filters
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['wallet_id'])) {
            $query->where('wallet_id', $filters['wallet_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference_id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = $filters['per_page'] ?? 15;
        $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $formattedTransactions = $transactions->getCollection()->map(function ($transaction) {
            return $this->formatTransactionForAdmin($transaction);
        });

        return [
            'transactions' => $formattedTransactions,
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
                'has_more_pages' => $transactions->hasMorePages()
            ]
        ];
    }

    /**
     * Get all withdrawals with user information
     */
    public function getAllWithdrawals(array $filters = []): array
    {
        $query = Withdrawal::with(['user', 'wallet', 'processedBy']);

        // Apply filters
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['method'])) {
            $query->where('method', $filters['method']);
        }

        if (isset($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('withdrawal_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = $filters['per_page'] ?? 15;
        $withdrawals = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $formattedWithdrawals = $withdrawals->getCollection()->map(function ($withdrawal) {
            return $this->formatWithdrawalForAdmin($withdrawal);
        });

        return [
            'withdrawals' => $formattedWithdrawals,
            'pagination' => [
                'current_page' => $withdrawals->currentPage(),
                'last_page' => $withdrawals->lastPage(),
                'per_page' => $withdrawals->perPage(),
                'total' => $withdrawals->total(),
                'from' => $withdrawals->firstItem(),
                'to' => $withdrawals->lastItem(),
                'has_more_pages' => $withdrawals->hasMorePages()
            ]
        ];
    }

    /**
     * Get wallet statistics for admin dashboard
     */
    public function getWalletStatistics(): array
    {
        $stats = [
            'overview' => [
                'total_wallets' => Wallet::count(),
                'active_wallets' => Wallet::where('is_active', true)->count(),
                'total_balance' => Wallet::sum('balance'),
                'total_pending_balance' => Wallet::sum('pending_balance'),
                'total_withdrawn_balance' => Wallet::sum('withdrawn_balance'),
                'total_transactions' => WalletTransaction::count(),
                'total_withdrawals' => Withdrawal::count(),
                'pending_withdrawals' => Withdrawal::where('status', Withdrawal::STATUS_PENDING)->count()
            ],
            'by_wallet_type' => Wallet::selectRaw('type, COUNT(*) as count, SUM(balance) as total_balance, SUM(pending_balance) as total_pending')
                ->groupBy('type')
                ->get()
                ->keyBy('type'),
            'by_transaction_type' => WalletTransaction::selectRaw('type, COUNT(*) as count, SUM(amount) as total_amount')
                ->groupBy('type')
                ->get()
                ->keyBy('type'),
            'by_transaction_category' => WalletTransaction::selectRaw('category, COUNT(*) as count, SUM(amount) as total_amount')
                ->groupBy('category')
                ->get()
                ->keyBy('category'),
            'by_withdrawal_status' => Withdrawal::selectRaw('status, COUNT(*) as count, SUM(amount) as total_amount')
                ->groupBy('status')
                ->get()
                ->keyBy('status'),
            'by_withdrawal_method' => Withdrawal::selectRaw('method, COUNT(*) as count, SUM(amount) as total_amount')
                ->groupBy('method')
                ->get()
                ->keyBy('method'),
            'daily_stats' => WalletTransaction::selectRaw('DATE(created_at) as date, COUNT(*) as transaction_count, SUM(amount) as total_amount')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get(),
            'top_users_by_balance' => Wallet::with('user')
                ->selectRaw('user_id, SUM(balance) as total_balance')
                ->groupBy('user_id')
                ->orderBy('total_balance', 'desc')
                ->limit(10)
                ->get(),
            'recent_activity' => [
                'recent_transactions' => WalletTransaction::with(['user', 'wallet'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($transaction) {
                        return $this->formatTransactionForAdmin($transaction);
                    }),
                'recent_withdrawals' => Withdrawal::with(['user', 'wallet'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($withdrawal) {
                        return $this->formatWithdrawalForAdmin($withdrawal);
                    })
            ]
        ];

        return $stats;
    }

    /**
     * Manually credit wallet
     */
    public function creditWallet(int $userId, string $walletType, float $amount, string $description, int $adminId): array
    {
        try {
            $transaction = $this->walletService->credit(
                $userId,
                $walletType,
                $amount,
                WalletTransaction::CATEGORY_ADMIN_CREDIT,
                $description,
                [
                    'admin_id' => $adminId,
                    'admin_action' => 'manual_credit',
                    'timestamp' => now()->toISOString()
                ]
            );

            return [
                'success' => true,
                'transaction' => $this->formatTransactionForAdmin($transaction),
                'message' => 'Wallet credited successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Manually debit wallet
     */
    public function debitWallet(int $userId, string $walletType, float $amount, string $description, int $adminId): array
    {
        try {
            $transaction = $this->walletService->debit(
                $userId,
                $walletType,
                $amount,
                WalletTransaction::CATEGORY_ADMIN_DEBIT,
                $description,
                [
                    'admin_id' => $adminId,
                    'admin_action' => 'manual_debit',
                    'timestamp' => now()->toISOString()
                ]
            );

            return [
                'success' => true,
                'transaction' => $this->formatTransactionForAdmin($transaction),
                'message' => 'Wallet debited successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Process withdrawal (approve/reject)
     */
    public function processWithdrawal(int $withdrawalId, string $status, int $adminId, string $adminNotes = null): array
    {
        try {
            $withdrawal = $this->walletService->processWithdrawal($withdrawalId, $status, $adminId, $adminNotes);

            return [
                'success' => true,
                'withdrawal' => $this->formatWithdrawalForAdmin($withdrawal),
                'message' => 'Withdrawal processed successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get user's wallet details
     */
    public function getUserWalletDetails(int $userId): array
    {
        $user = User::with(['wallets', 'walletTransactions' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(10);
        }, 'withdrawals' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(10);
        }])->findOrFail($userId);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'referral_code' => $user->referral_code,
                'package' => $user->package ? [
                    'id' => $user->package->id,
                    'name' => $user->package->name,
                    'price' => $user->package->price
                ] : null
            ],
            'wallets' => $user->wallets()->get()->map(function ($wallet) {
                return $this->formatWalletForAdmin($wallet);
            }),
            'recent_transactions' => $user->walletTransactions->map(function ($transaction) {
                return $this->formatTransactionForAdmin($transaction);
            }),
            'recent_withdrawals' => $user->withdrawals->map(function ($withdrawal) {
                return $this->formatWithdrawalForAdmin($withdrawal);
            }),
            'summary' => [
                'total_balance' => $user->wallets()->sum('balance'),
                'total_pending' => $user->wallets()->sum('pending_balance'),
                'total_withdrawn' => $user->wallets()->sum('withdrawn_balance'),
                'total_transactions' => $user->walletTransactions()->count(),
                'total_withdrawals' => $user->withdrawals()->count()
            ]
        ];
    }

    /**
     * Format wallet for admin display
     */
    private function formatWalletForAdmin(Wallet $wallet): array
    {
        return [
            'id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'user_name' => $wallet->user->name,
            'user_email' => $wallet->user->email,
            'type' => $wallet->type,
            'display_name' => $this->getWalletDisplayName($wallet->type),
            'balance' => number_format($wallet->balance, 2),
            'pending_balance' => number_format($wallet->pending_balance, 2),
            'withdrawn_balance' => number_format($wallet->withdrawn_balance, 2),
            'available_balance' => number_format($wallet->available_balance, 2),
            'total_balance' => number_format($wallet->total_balance, 2),
            'is_active' => $wallet->is_active,
            'settings' => $wallet->settings,
            'created_at' => $wallet->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $wallet->updated_at->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Format transaction for admin display
     */
    private function formatTransactionForAdmin(WalletTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'user_id' => $transaction->user_id,
            'user_name' => $transaction->user->name,
            'user_email' => $transaction->user->email,
            'wallet_id' => $transaction->wallet_id,
            'wallet_type' => $transaction->wallet->type,
            'wallet_display_name' => $this->getWalletDisplayName($transaction->wallet->type),
            'type' => $transaction->type,
            'category' => $transaction->category,
            'amount' => number_format($transaction->amount, 2),
            'balance_before' => number_format($transaction->balance_before, 2),
            'balance_after' => number_format($transaction->balance_after, 2),
            'reference_id' => $transaction->reference_id,
            'description' => $transaction->description,
            'status' => $transaction->status,
            'metadata' => $transaction->metadata,
            'created_at' => $transaction->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $transaction->updated_at->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Format withdrawal for admin display
     */
    private function formatWithdrawalForAdmin(Withdrawal $withdrawal): array
    {
        return [
            'id' => $withdrawal->id,
            'user_id' => $withdrawal->user_id,
            'user_name' => $withdrawal->user->name,
            'user_email' => $withdrawal->user->email,
            'wallet_id' => $withdrawal->wallet_id,
            'wallet_type' => $withdrawal->wallet->type,
            'wallet_display_name' => $this->getWalletDisplayName($withdrawal->wallet->type),
            'withdrawal_id' => $withdrawal->withdrawal_id,
            'amount' => number_format($withdrawal->amount, 2),
            'fee' => number_format($withdrawal->fee, 2),
            'net_amount' => number_format($withdrawal->net_amount, 2),
            'method' => $withdrawal->method,
            'method_display_name' => $this->getWithdrawalMethodDisplayName($withdrawal->method),
            'payment_details' => $withdrawal->payment_details,
            'status' => $withdrawal->status,
            'status_display_name' => $this->getWithdrawalStatusDisplayName($withdrawal->status),
            'user_notes' => $withdrawal->user_notes,
            'admin_notes' => $withdrawal->admin_notes,
            'processed_by' => $withdrawal->processedBy ? $withdrawal->processedBy->name : null,
            'processed_at' => $withdrawal->processed_at ? $withdrawal->processed_at->format('Y-m-d H:i:s') : null,
            'created_at' => $withdrawal->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $withdrawal->updated_at->format('Y-m-d H:i:s')
        ];
    }

    // Helper methods
    private function getWalletDisplayName(string $type): string
    {
        return match ($type) {
            Wallet::TYPE_EARNING => 'Earning Wallet',
            Wallet::TYPE_BONUS => 'Bonus Wallet',
            Wallet::TYPE_REWARD => 'Reward Wallet',
            Wallet::TYPE_HOLDING => 'Holding Wallet',
            Wallet::TYPE_COMMISSION => 'Commission Wallet',
            default => ucfirst($type) . ' Wallet'
        };
    }

    private function getWithdrawalMethodDisplayName(string $method): string
    {
        return match ($method) {
            Withdrawal::METHOD_BANK_TRANSFER => 'Bank Transfer',
            Withdrawal::METHOD_DIGITAL_WALLET => 'Digital Wallet',
            Withdrawal::METHOD_CRYPTOCURRENCY => 'Cryptocurrency',
            Withdrawal::METHOD_CHECK => 'Check',
            Withdrawal::METHOD_CASH_PICKUP => 'Cash Pickup',
            default => ucfirst(str_replace('_', ' ', $method))
        };
    }

    private function getWithdrawalStatusDisplayName(string $status): string
    {
        return match ($status) {
            Withdrawal::STATUS_PENDING => 'Pending Review',
            Withdrawal::STATUS_APPROVED => 'Approved',
            Withdrawal::STATUS_PROCESSING => 'Processing',
            Withdrawal::STATUS_COMPLETED => 'Completed',
            Withdrawal::STATUS_FAILED => 'Failed',
            Withdrawal::STATUS_CANCELLED => 'Cancelled',
            Withdrawal::STATUS_REJECTED => 'Rejected',
            default => ucfirst($status)
        };
    }
}
