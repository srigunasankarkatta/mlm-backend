<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all transactions with filters and pagination
     */
    public function index(Request $request)
    {
        $query = Transaction::with(['user', 'package']);

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by type
        if ($request->has('type') && in_array($request->type, [
            Transaction::TYPE_PURCHASE,
            Transaction::TYPE_REFUND,
            Transaction::TYPE_COMMISSION,
            Transaction::TYPE_BONUS
        ])) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status') && in_array($request->status, [
            Transaction::STATUS_PENDING,
            Transaction::STATUS_COMPLETED,
            Transaction::STATUS_FAILED,
            Transaction::STATUS_CANCELLED
        ])) {
            $query->where('status', $request->status);
        }

        // Filter by package
        if ($request->has('package_id')) {
            $query->where('package_id', $request->package_id);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Search by transaction ID, user name, or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Sort by created_at desc by default
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        if (in_array($sortBy, ['created_at', 'amount', 'type', 'status', 'user_id'])) {
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
        ], 'Transactions fetched successfully');
    }

    /**
     * Get transaction statistics
     */
    public function stats()
    {
        $stats = [
            'total_transactions' => Transaction::count(),
            'total_amount' => Transaction::where('status', Transaction::STATUS_COMPLETED)->sum('amount'),
            'total_purchases' => Transaction::where('type', Transaction::TYPE_PURCHASE)
                ->where('status', Transaction::STATUS_COMPLETED)
                ->sum('amount'),
            'total_commissions' => Transaction::where('type', Transaction::TYPE_COMMISSION)
                ->where('status', Transaction::STATUS_COMPLETED)
                ->sum('amount'),
            'total_bonuses' => Transaction::where('type', Transaction::TYPE_BONUS)
                ->where('status', Transaction::STATUS_COMPLETED)
                ->sum('amount'),
            'pending_transactions' => Transaction::where('status', Transaction::STATUS_PENDING)->count(),
            'failed_transactions' => Transaction::where('status', Transaction::STATUS_FAILED)->count(),
            'by_type' => Transaction::selectRaw('type, COUNT(*) as count, SUM(amount) as total_amount')
                ->where('status', Transaction::STATUS_COMPLETED)
                ->groupBy('type')
                ->get()
                ->keyBy('type'),
            'by_status' => Transaction::selectRaw('status, COUNT(*) as count, SUM(amount) as total_amount')
                ->groupBy('status')
                ->get()
                ->keyBy('status'),
            'daily_stats' => Transaction::selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(amount) as total_amount')
                ->where('status', Transaction::STATUS_COMPLETED)
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get(),
            'top_users' => Transaction::with('user')
                ->selectRaw('user_id, COUNT(*) as transaction_count, SUM(amount) as total_amount')
                ->where('status', Transaction::STATUS_COMPLETED)
                ->groupBy('user_id')
                ->orderBy('total_amount', 'desc')
                ->limit(10)
                ->get()
        ];

        return $this->successResponse($stats, 'Transaction statistics fetched successfully');
    }

    /**
     * Get specific transaction details
     */
    public function show(Transaction $transaction)
    {
        $transaction->load(['user', 'package']);

        return $this->successResponse($transaction, 'Transaction details fetched successfully');
    }

    /**
     * Update transaction status
     */
    public function updateStatus(Request $request, Transaction $transaction)
    {
        $request->validate([
            'status' => 'required|in:pending,completed,failed,cancelled',
            'description' => 'nullable|string|max:500'
        ]);

        $oldStatus = $transaction->status;
        $transaction->status = $request->status;

        if ($request->has('description')) {
            $transaction->description = $request->description;
        }

        // Add status change to metadata
        $metadata = $transaction->metadata ?? [];
        $metadata['status_changes'][] = [
            'from' => $oldStatus,
            'to' => $request->status,
            'changed_at' => now()->toISOString(),
            'changed_by' => Auth::id()
        ];
        $transaction->metadata = $metadata;

        $transaction->save();

        return $this->successResponse([
            'transaction' => $transaction,
            'status_change' => [
                'from' => $oldStatus,
                'to' => $request->status,
                'changed_at' => $transaction->updated_at
            ]
        ], 'Transaction status updated successfully');
    }
}
