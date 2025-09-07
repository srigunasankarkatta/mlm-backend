<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get user's transaction history
     */
    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $query = $user->transactions()->with(['package']);

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

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Search by transaction ID or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sort by created_at desc by default
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        if (in_array($sortBy, ['created_at', 'amount', 'type', 'status'])) {
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
     * Get a specific transaction details
     */
    public function show($id)
    {
        /** @var User $user */
        $user = Auth::user();
        $transaction = $user->transactions()->with(['package'])->findOrFail($id);

        return $this->successResponse($transaction, 'Transaction details fetched successfully');
    }

    /**
     * Get transaction summary/statistics
     */
    public function summary()
    {
        /** @var User $user */
        $user = Auth::user();

        $summary = [
            'total_transactions' => $user->transactions()->count(),
            'total_spent' => $user->transactions()
                ->where('type', Transaction::TYPE_PURCHASE)
                ->where('status', Transaction::STATUS_COMPLETED)
                ->sum('amount'),
            'total_earned' => $user->transactions()
                ->whereIn('type', [Transaction::TYPE_COMMISSION, Transaction::TYPE_BONUS])
                ->where('status', Transaction::STATUS_COMPLETED)
                ->sum('amount'),
            'pending_transactions' => $user->transactions()
                ->where('status', Transaction::STATUS_PENDING)
                ->count(),
            'failed_transactions' => $user->transactions()
                ->where('status', Transaction::STATUS_FAILED)
                ->count(),
            'by_type' => $user->transactions()
                ->selectRaw('type, COUNT(*) as count, SUM(amount) as total_amount')
                ->where('status', Transaction::STATUS_COMPLETED)
                ->groupBy('type')
                ->get()
                ->keyBy('type'),
            'by_status' => $user->transactions()
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get()
                ->keyBy('status'),
            'recent_transactions' => $user->transactions()
                ->with(['package'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
        ];

        return $this->successResponse($summary, 'Transaction summary fetched successfully');
    }

    /**
     * Get transactions by package
     */
    public function byPackage($packageId)
    {
        /** @var User $user */
        $user = Auth::user();

        $transactions = $user->transactions()
            ->where('package_id', $packageId)
            ->with(['package'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($transactions, 'Package transactions fetched successfully');
    }

    /**
     * Export transactions (CSV format)
     */
    public function export(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $query = $user->transactions()->with(['package']);

        // Apply same filters as index method
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        $csvData = [];
        $csvData[] = [
            'Transaction ID',
            'Type',
            'Status',
            'Amount',
            'Package',
            'Payment Method',
            'Description',
            'Date'
        ];

        foreach ($transactions as $transaction) {
            $csvData[] = [
                $transaction->transaction_id,
                $transaction->type,
                $transaction->status,
                $transaction->amount,
                $transaction->package ? $transaction->package->name : 'N/A',
                $transaction->payment_method ?? 'N/A',
                $transaction->description,
                $transaction->created_at->format('Y-m-d H:i:s')
            ];
        }

        $filename = 'transactions_' . $user->id . '_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $callback = function () use ($csvData) {
            $file = fopen('php://output', 'w');
            foreach ($csvData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
