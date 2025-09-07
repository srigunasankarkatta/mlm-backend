<?php

namespace App\Services\Customer;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerWalletService
{
    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Get customer's wallet balances with formatted data
     */
    public function getCustomerWalletBalances(int $userId): array
    {
        $balances = $this->walletService->getUserWalletBalances($userId);
        
        $formattedBalances = [];
        foreach ($balances as $type => $balance) {
            $formattedBalances[$type] = [
                'type' => $type,
                'display_name' => $this->getWalletDisplayName($type),
                'balance' => number_format($balance['balance'], 2),
                'pending_balance' => number_format($balance['pending_balance'], 2),
                'withdrawn_balance' => number_format($balance['withdrawn_balance'], 2),
                'available_balance' => number_format($balance['available_balance'], 2),
                'total_balance' => number_format($balance['total_balance'], 2),
                'is_active' => $balance['is_active'],
                'withdrawal_enabled' => $this->isWithdrawalEnabled($type),
                'icon' => $this->getWalletIcon($type),
                'color' => $this->getWalletColor($type)
            ];
        }

        return $formattedBalances;
    }

    /**
     * Get customer's transaction history with enhanced data
     */
    public function getCustomerTransactions(int $userId, array $filters = []): array
    {
        $query = WalletTransaction::where('user_id', $userId)
            ->with(['wallet'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['wallet_type'])) {
            $query->whereHas('wallet', function ($q) use ($filters) {
                $q->where('type', $filters['wallet_type']);
            });
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
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = $filters['per_page'] ?? 15;
        $transactions = $query->paginate($perPage);

        $formattedTransactions = $transactions->getCollection()->map(function ($transaction) {
            return $this->formatTransaction($transaction);
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
     * Get customer's wallet summary with enhanced analytics
     */
    public function getCustomerWalletSummary(int $userId): array
    {
        $user = User::findOrFail($userId);
        
        $summary = [
            'overview' => [
                'total_balance' => number_format($this->getTotalBalance($userId), 2),
                'total_earned' => number_format($this->getTotalEarned($userId), 2),
                'total_spent' => number_format($this->getTotalSpent($userId), 2),
                'pending_withdrawals' => number_format($this->getPendingWithdrawals($userId), 2),
                'completed_withdrawals' => number_format($this->getCompletedWithdrawals($userId), 2),
                'net_earnings' => number_format($this->getNetEarnings($userId), 2)
            ],
            'wallet_breakdown' => $this->getWalletBreakdown($userId),
            'income_breakdown' => $this->getIncomeBreakdown($userId),
            'expense_breakdown' => $this->getExpenseBreakdown($userId),
            'monthly_stats' => $this->getMonthlyStats($userId),
            'recent_activity' => $this->getRecentActivity($userId),
            'withdrawal_stats' => $this->getWithdrawalStats($userId)
        ];

        return $summary;
    }

    /**
     * Create withdrawal request with enhanced validation
     */
    public function createWithdrawalRequest(int $userId, array $data): array
    {
        try {
            $withdrawal = $this->walletService->createWithdrawal(
                $userId,
                $data['wallet_type'],
                $data['amount'],
                $data['method'],
                $data['payment_details'],
                $data['notes'] ?? null
            );

            return [
                'success' => true,
                'withdrawal' => $this->formatWithdrawal($withdrawal),
                'message' => 'Withdrawal request submitted successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get customer's withdrawal history
     */
    public function getCustomerWithdrawals(int $userId, array $filters = []): array
    {
        $query = Withdrawal::where('user_id', $userId)
            ->with(['wallet', 'processedBy'])
            ->orderBy('created_at', 'desc');

        // Apply filters
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
            $query->where('withdrawal_id', 'like', "%{$search}%");
        }

        $perPage = $filters['per_page'] ?? 15;
        $withdrawals = $query->paginate($perPage);

        $formattedWithdrawals = $withdrawals->getCollection()->map(function ($withdrawal) {
            return $this->formatWithdrawal($withdrawal);
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
     * Get withdrawal limits and settings
     */
    public function getWithdrawalLimits(int $userId): array
    {
        $user = User::findOrFail($userId);
        $limits = [];
        
        foreach ($user->wallets as $wallet) {
            $settings = $wallet->settings ?? [];
            $limits[$wallet->type] = [
                'wallet_type' => $wallet->type,
                'display_name' => $this->getWalletDisplayName($wallet->type),
                'withdrawal_enabled' => $settings['withdrawal_enabled'] ?? true,
                'min_withdrawal' => number_format($settings['min_withdrawal'] ?? 10.00, 2),
                'max_withdrawal' => number_format($settings['max_withdrawal'] ?? 5000.00, 2),
                'daily_limit' => number_format($settings['daily_limit'] ?? 500.00, 2),
                'monthly_limit' => number_format($settings['monthly_limit'] ?? 5000.00, 2),
                'available_balance' => number_format($wallet->available_balance, 2),
                'pending_balance' => number_format($wallet->pending_balance, 2),
                'withdrawal_methods' => $this->getAvailableWithdrawalMethods($wallet->type)
            ];
        }

        return $limits;
    }

    /**
     * Get available withdrawal methods for wallet type
     */
    private function getAvailableWithdrawalMethods(string $walletType): array
    {
        $methods = [
            'bank_transfer' => [
                'id' => 'bank_transfer',
                'name' => 'Bank Transfer',
                'fee_percentage' => 2,
                'processing_time' => '1-3 business days',
                'icon' => 'bank',
                'enabled' => true
            ],
            'digital_wallet' => [
                'id' => 'digital_wallet',
                'name' => 'Digital Wallet',
                'fee_percentage' => 3,
                'processing_time' => '1-2 business days',
                'icon' => 'wallet',
                'enabled' => true
            ],
            'cryptocurrency' => [
                'id' => 'cryptocurrency',
                'name' => 'Cryptocurrency',
                'fee_percentage' => 5,
                'processing_time' => '2-5 business days',
                'icon' => 'crypto',
                'enabled' => true
            ],
            'check' => [
                'id' => 'check',
                'name' => 'Check',
                'fee_percentage' => 1,
                'processing_time' => '5-7 business days',
                'icon' => 'check',
                'enabled' => true
            ],
            'cash_pickup' => [
                'id' => 'cash_pickup',
                'name' => 'Cash Pickup',
                'fee_percentage' => 4,
                'processing_time' => '1-2 business days',
                'icon' => 'cash',
                'enabled' => true
            ]
        ];

        // Some methods might not be available for certain wallet types
        if ($walletType === Wallet::TYPE_REWARD || $walletType === Wallet::TYPE_HOLDING) {
            return []; // No withdrawals allowed
        }

        return array_values($methods);
    }

    /**
     * Format transaction for customer display
     */
    private function formatTransaction(WalletTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'reference_id' => $transaction->reference_id,
            'type' => $transaction->type,
            'category' => $transaction->category,
            'amount' => number_format($transaction->amount, 2),
            'balance_before' => number_format($transaction->balance_before, 2),
            'balance_after' => number_format($transaction->balance_after, 2),
            'description' => $transaction->description,
            'status' => $transaction->status,
            'wallet_type' => $transaction->wallet->type,
            'wallet_display_name' => $this->getWalletDisplayName($transaction->wallet->type),
            'formatted_date' => $transaction->created_at->format('M d, Y'),
            'formatted_time' => $transaction->created_at->format('h:i A'),
            'iso_date' => $transaction->created_at->toISOString(),
            'metadata' => $transaction->metadata,
            'is_credit' => $transaction->type === WalletTransaction::TYPE_CREDIT,
            'is_debit' => $transaction->type === WalletTransaction::TYPE_DEBIT,
            'icon' => $this->getTransactionIcon($transaction->category),
            'color' => $this->getTransactionColor($transaction->type)
        ];
    }

    /**
     * Format withdrawal for customer display
     */
    private function formatWithdrawal(Withdrawal $withdrawal): array
    {
        return [
            'id' => $withdrawal->id,
            'withdrawal_id' => $withdrawal->withdrawal_id,
            'amount' => number_format($withdrawal->amount, 2),
            'fee' => number_format($withdrawal->fee, 2),
            'net_amount' => number_format($withdrawal->net_amount, 2),
            'method' => $withdrawal->method,
            'method_display_name' => $this->getWithdrawalMethodDisplayName($withdrawal->method),
            'status' => $withdrawal->status,
            'status_display_name' => $this->getWithdrawalStatusDisplayName($withdrawal->status),
            'wallet_type' => $withdrawal->wallet->type,
            'wallet_display_name' => $this->getWalletDisplayName($withdrawal->wallet->type),
            'user_notes' => $withdrawal->user_notes,
            'admin_notes' => $withdrawal->admin_notes,
            'processed_by' => $withdrawal->processedBy ? $withdrawal->processedBy->name : null,
            'processed_at' => $withdrawal->processed_at ? $withdrawal->processed_at->format('M d, Y h:i A') : null,
            'formatted_date' => $withdrawal->created_at->format('M d, Y'),
            'formatted_time' => $withdrawal->created_at->format('h:i A'),
            'iso_date' => $withdrawal->created_at->toISOString(),
            'is_pending' => $withdrawal->is_pending,
            'is_completed' => $withdrawal->is_completed,
            'is_failed' => $withdrawal->is_failed,
            'icon' => $this->getWithdrawalIcon($withdrawal->method),
            'color' => $this->getWithdrawalStatusColor($withdrawal->status)
        ];
    }

    // Helper methods for display names and formatting
    private function getWalletDisplayName(string $type): string
    {
        return match($type) {
            Wallet::TYPE_EARNING => 'Earning Wallet',
            Wallet::TYPE_BONUS => 'Bonus Wallet',
            Wallet::TYPE_REWARD => 'Reward Wallet',
            Wallet::TYPE_HOLDING => 'Holding Wallet',
            Wallet::TYPE_COMMISSION => 'Commission Wallet',
            default => ucfirst($type) . ' Wallet'
        };
    }

    private function getWalletIcon(string $type): string
    {
        return match($type) {
            Wallet::TYPE_EARNING => 'dollar-sign',
            Wallet::TYPE_BONUS => 'gift',
            Wallet::TYPE_REWARD => 'star',
            Wallet::TYPE_HOLDING => 'lock',
            Wallet::TYPE_COMMISSION => 'trending-up',
            default => 'wallet'
        };
    }

    private function getWalletColor(string $type): string
    {
        return match($type) {
            Wallet::TYPE_EARNING => 'green',
            Wallet::TYPE_BONUS => 'blue',
            Wallet::TYPE_REWARD => 'purple',
            Wallet::TYPE_HOLDING => 'gray',
            Wallet::TYPE_COMMISSION => 'orange',
            default => 'gray'
        };
    }

    private function isWithdrawalEnabled(string $type): bool
    {
        return !in_array($type, [Wallet::TYPE_REWARD, Wallet::TYPE_HOLDING]);
    }

    private function getTransactionIcon(string $category): string
    {
        return match($category) {
            'direct_income' => 'users',
            'level_income' => 'layers',
            'club_income' => 'award',
            'auto_pool' => 'database',
            'bonus' => 'gift',
            'package_purchase' => 'shopping-cart',
            'withdrawal' => 'arrow-up-right',
            'transfer' => 'arrow-left-right',
            'admin_credit' => 'plus-circle',
            'admin_debit' => 'minus-circle',
            'fee' => 'credit-card',
            'penalty' => 'alert-triangle',
            default => 'dollar-sign'
        };
    }

    private function getTransactionColor(string $type): string
    {
        return match($type) {
            WalletTransaction::TYPE_CREDIT => 'green',
            WalletTransaction::TYPE_DEBIT => 'red',
            WalletTransaction::TYPE_TRANSFER_IN => 'blue',
            WalletTransaction::TYPE_TRANSFER_OUT => 'orange',
            default => 'gray'
        };
    }

    private function getWithdrawalMethodDisplayName(string $method): string
    {
        return match($method) {
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
        return match($status) {
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

    private function getWithdrawalIcon(string $method): string
    {
        return match($method) {
            Withdrawal::METHOD_BANK_TRANSFER => 'bank',
            Withdrawal::METHOD_DIGITAL_WALLET => 'wallet',
            Withdrawal::METHOD_CRYPTOCURRENCY => 'bitcoin',
            Withdrawal::METHOD_CHECK => 'file-text',
            Withdrawal::METHOD_CASH_PICKUP => 'map-pin',
            default => 'credit-card'
        };
    }

    private function getWithdrawalStatusColor(string $status): string
    {
        return match($status) {
            Withdrawal::STATUS_PENDING => 'yellow',
            Withdrawal::STATUS_APPROVED => 'blue',
            Withdrawal::STATUS_PROCESSING => 'purple',
            Withdrawal::STATUS_COMPLETED => 'green',
            Withdrawal::STATUS_FAILED => 'red',
            Withdrawal::STATUS_CANCELLED => 'gray',
            Withdrawal::STATUS_REJECTED => 'red',
            default => 'gray'
        };
    }

    // Helper methods for summary calculations
    private function getTotalBalance(int $userId): float
    {
        return Wallet::where('user_id', $userId)->sum('balance');
    }

    private function getTotalEarned(int $userId): float
    {
        return WalletTransaction::where('user_id', $userId)
            ->whereIn('category', [
                WalletTransaction::CATEGORY_DIRECT_INCOME,
                WalletTransaction::CATEGORY_LEVEL_INCOME,
                WalletTransaction::CATEGORY_CLUB_INCOME,
                WalletTransaction::CATEGORY_AUTO_POOL,
                WalletTransaction::CATEGORY_BONUS
            ])
            ->completed()
            ->sum('amount');
    }

    private function getTotalSpent(int $userId): float
    {
        return WalletTransaction::where('user_id', $userId)
            ->whereIn('category', [
                WalletTransaction::CATEGORY_PACKAGE_PURCHASE,
                WalletTransaction::CATEGORY_WITHDRAWAL,
                WalletTransaction::CATEGORY_FEE,
                WalletTransaction::CATEGORY_PENALTY
            ])
            ->completed()
            ->sum('amount');
    }

    private function getPendingWithdrawals(int $userId): float
    {
        return Withdrawal::where('user_id', $userId)->pending()->sum('amount');
    }

    private function getCompletedWithdrawals(int $userId): float
    {
        return Withdrawal::where('user_id', $userId)->completed()->sum('amount');
    }

    private function getNetEarnings(int $userId): float
    {
        return $this->getTotalEarned($userId) - $this->getTotalSpent($userId);
    }

    private function getWalletBreakdown(int $userId): array
    {
        $wallets = Wallet::where('user_id', $userId)->get();
        $breakdown = [];
        
        foreach ($wallets as $wallet) {
            $breakdown[$wallet->type] = [
                'type' => $wallet->type,
                'display_name' => $this->getWalletDisplayName($wallet->type),
                'balance' => number_format($wallet->balance, 2),
                'percentage' => $this->getTotalBalance($userId) > 0 ? 
                    round(($wallet->balance / $this->getTotalBalance($userId)) * 100, 2) : 0
            ];
        }
        
        return $breakdown;
    }

    private function getIncomeBreakdown(int $userId): array
    {
        return WalletTransaction::where('user_id', $userId)
            ->whereIn('category', [
                WalletTransaction::CATEGORY_DIRECT_INCOME,
                WalletTransaction::CATEGORY_LEVEL_INCOME,
                WalletTransaction::CATEGORY_CLUB_INCOME,
                WalletTransaction::CATEGORY_AUTO_POOL,
                WalletTransaction::CATEGORY_BONUS
            ])
            ->completed()
            ->selectRaw('category, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('category')
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->category,
                    'display_name' => ucfirst(str_replace('_', ' ', $item->category)),
                    'count' => $item->count,
                    'total_amount' => number_format($item->total_amount, 2)
                ];
            })
            ->keyBy('category')
            ->toArray();
    }

    private function getExpenseBreakdown(int $userId): array
    {
        return WalletTransaction::where('user_id', $userId)
            ->whereIn('category', [
                WalletTransaction::CATEGORY_PACKAGE_PURCHASE,
                WalletTransaction::CATEGORY_WITHDRAWAL,
                WalletTransaction::CATEGORY_FEE,
                WalletTransaction::CATEGORY_PENALTY
            ])
            ->completed()
            ->selectRaw('category, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('category')
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->category,
                    'display_name' => ucfirst(str_replace('_', ' ', $item->category)),
                    'count' => $item->count,
                    'total_amount' => number_format($item->total_amount, 2)
                ];
            })
            ->keyBy('category')
            ->toArray();
    }

    private function getMonthlyStats(int $userId): array
    {
        return WalletTransaction::where('user_id', $userId)
            ->completed()
            ->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, 
                        SUM(CASE WHEN type = "credit" THEN amount ELSE 0 END) as credits,
                        SUM(CASE WHEN type = "debit" THEN amount ELSE 0 END) as debits')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->month,
                    'credits' => number_format($item->credits, 2),
                    'debits' => number_format($item->debits, 2),
                    'net' => number_format($item->credits - $item->debits, 2)
                ];
            })
            ->toArray();
    }

    private function getRecentActivity(int $userId): array
    {
        return WalletTransaction::where('user_id', $userId)
            ->with(['wallet'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($transaction) {
                return $this->formatTransaction($transaction);
            })
            ->toArray();
    }

    private function getWithdrawalStats(int $userId): array
    {
        $stats = Withdrawal::where('user_id', $userId)
            ->selectRaw('status, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return [
            'pending' => [
                'count' => $stats->get('pending')->count ?? 0,
                'amount' => number_format($stats->get('pending')->total_amount ?? 0, 2)
            ],
            'completed' => [
                'count' => $stats->get('completed')->count ?? 0,
                'amount' => number_format($stats->get('completed')->total_amount ?? 0, 2)
            ],
            'failed' => [
                'count' => $stats->get('failed')->count ?? 0,
                'amount' => number_format($stats->get('failed')->total_amount ?? 0, 2)
            ],
            'total_requests' => $stats->sum('count'),
            'total_amount' => number_format($stats->sum('total_amount'), 2)
        ];
    }
}


