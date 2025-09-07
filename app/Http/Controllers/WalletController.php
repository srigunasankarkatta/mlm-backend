<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\WalletService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    use ApiResponseTrait;

    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Get user's wallet balances
     */
    public function balance()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $balances = $this->walletService->getUserWalletBalances($user->id);

        return $this->successResponse($balances, 'Wallet balances fetched successfully');
    }

    /**
     * Get wallet transaction history
     */
    public function transactions(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $query = $user->walletTransactions()->with(['wallet']);

        // Filter by wallet type
        if ($request->has('wallet_type')) {
            $query->whereHas('wallet', function ($q) use ($request) {
                $q->where('type', $request->wallet_type);
            });
        }

        // Filter by transaction type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Search by reference ID or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sort by created_at desc by default
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        if (in_array($sortBy, ['created_at', 'amount', 'type', 'category', 'status'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = $request->get('per_page', 15);
        $transactions = $query->paginate($perPage);

        return $this->successResponse([
            'transactions' => $transactions->items(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ]
        ], 'Wallet transactions fetched successfully');
    }

    /**
     * Get wallet summary/statistics
     */
    public function summary()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $summary = [
            'total_balance' => $user->walletTransactions()->credits()->completed()->sum('amount') -
                $user->walletTransactions()->debits()->completed()->sum('amount'),
            'total_earned' => $user->walletTransactions()
                ->whereIn('category', [
                    WalletTransaction::CATEGORY_DIRECT_INCOME,
                    WalletTransaction::CATEGORY_LEVEL_INCOME,
                    WalletTransaction::CATEGORY_CLUB_INCOME,
                    WalletTransaction::CATEGORY_AUTO_POOL,
                    WalletTransaction::CATEGORY_BONUS
                ])
                ->completed()
                ->sum('amount'),
            'total_spent' => $user->walletTransactions()
                ->whereIn('category', [
                    WalletTransaction::CATEGORY_PACKAGE_PURCHASE,
                    WalletTransaction::CATEGORY_WITHDRAWAL,
                    WalletTransaction::CATEGORY_FEE,
                    WalletTransaction::CATEGORY_PENALTY
                ])
                ->completed()
                ->sum('amount'),
            'pending_withdrawals' => $user->withdrawals()->pending()->sum('amount'),
            'completed_withdrawals' => $user->withdrawals()->completed()->sum('amount'),
            'by_category' => $user->walletTransactions()
                ->selectRaw('category, COUNT(*) as count, SUM(amount) as total_amount')
                ->completed()
                ->groupBy('category')
                ->get()
                ->keyBy('category'),
            'by_wallet_type' => $user->wallets()
                ->selectRaw('type, SUM(balance) as balance, SUM(pending_balance) as pending_balance')
                ->groupBy('type')
                ->get()
                ->keyBy('type'),
            'recent_transactions' => $user->walletTransactions()
                ->with(['wallet'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
        ];

        return $this->successResponse($summary, 'Wallet summary fetched successfully');
    }

    /**
     * Request withdrawal
     */
    public function withdraw(Request $request)
    {
        $request->validate([
            'wallet_type' => 'required|in:earning,bonus,commission',
            'amount' => 'required|numeric|min:10',
            'method' => 'required|in:bank_transfer,digital_wallet,cryptocurrency,check,cash_pickup',
            'payment_details' => 'required|array',
            'payment_details.account_name' => 'required|string',
            'payment_details.account_number' => 'required|string',
            'notes' => 'nullable|string|max:500'
        ]);

        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            $withdrawal = $this->walletService->createWithdrawal(
                $user->id,
                $request->wallet_type,
                $request->amount,
                $request->method,
                $request->payment_details,
                $request->notes
            );

            return $this->successResponse([
                'withdrawal' => $withdrawal
            ], 'Withdrawal request submitted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Get withdrawal history
     */
    public function withdrawals(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $query = $user->withdrawals()->with(['wallet']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by method
        if ($request->has('method')) {
            $query->where('method', $request->method);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Search by withdrawal ID
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('withdrawal_id', 'like', "%{$search}%");
        }

        // Sort by created_at desc by default
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        if (in_array($sortBy, ['created_at', 'amount', 'status', 'method'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = $request->get('per_page', 15);
        $withdrawals = $query->paginate($perPage);

        return $this->successResponse([
            'withdrawals' => $withdrawals->items(),
            'pagination' => [
                'current_page' => $withdrawals->currentPage(),
                'last_page' => $withdrawals->lastPage(),
                'per_page' => $withdrawals->perPage(),
                'total' => $withdrawals->total(),
                'from' => $withdrawals->firstItem(),
                'to' => $withdrawals->lastItem(),
            ]
        ], 'Withdrawals fetched successfully');
    }
}
