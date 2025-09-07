<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminWalletService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AdminWalletController extends Controller
{
    use ApiResponseTrait;

    protected $adminWalletService;

    public function __construct(AdminWalletService $adminWalletService)
    {
        $this->adminWalletService = $adminWalletService;
    }

    /**
     * Get all wallets with filtering and pagination
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWallets(Request $request)
    {
        try {
            $filters = $request->only([
                'user_id',
                'type',
                'is_active',
                'search',
                'per_page'
            ]);

            $result = $this->adminWalletService->getAllWallets($filters);

            return $this->successResponse($result, 'Wallets retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve wallets: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all wallet transactions with filtering and pagination
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTransactions(Request $request)
    {
        try {
            $filters = $request->only([
                'user_id',
                'wallet_id',
                'type',
                'category',
                'status',
                'from_date',
                'to_date',
                'search',
                'per_page'
            ]);

            $result = $this->adminWalletService->getAllWalletTransactions($filters);

            return $this->successResponse($result, 'Wallet transactions retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve transactions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all withdrawals with filtering and pagination
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWithdrawals(Request $request)
    {
        try {
            $filters = $request->only([
                'user_id',
                'status',
                'method',
                'from_date',
                'to_date',
                'search',
                'per_page'
            ]);

            $result = $this->adminWalletService->getAllWithdrawals($filters);

            return $this->successResponse($result, 'Withdrawals retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve withdrawals: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get wallet statistics for admin dashboard
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatistics()
    {
        try {
            $stats = $this->adminWalletService->getWalletStatistics();

            return $this->successResponse($stats, 'Wallet statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get specific wallet details
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWallet($id)
    {
        try {
            $wallet = \App\Models\Wallet::with(['user', 'transactions' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(10);
            }])->findOrFail($id);

            $formattedWallet = $this->adminWalletService->formatWalletForAdmin($wallet);

            return $this->successResponse($formattedWallet, 'Wallet details retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Wallet not found', 404);
        }
    }

    /**
     * Get specific transaction details
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTransaction($id)
    {
        try {
            $transaction = \App\Models\WalletTransaction::with(['user', 'wallet'])->findOrFail($id);

            $formattedTransaction = $this->adminWalletService->formatTransactionForAdmin($transaction);

            return $this->successResponse($formattedTransaction, 'Transaction details retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Transaction not found', 404);
        }
    }

    /**
     * Get specific withdrawal details
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWithdrawal($id)
    {
        try {
            $withdrawal = \App\Models\Withdrawal::with(['user', 'wallet', 'processedBy'])->findOrFail($id);

            $formattedWithdrawal = $this->adminWalletService->formatWithdrawalForAdmin($withdrawal);

            return $this->successResponse($formattedWithdrawal, 'Withdrawal details retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Withdrawal not found', 404);
        }
    }

    /**
     * Get user's wallet details
     * 
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserWallets($userId)
    {
        try {
            $userWallets = $this->adminWalletService->getUserWalletDetails($userId);

            return $this->successResponse($userWallets, 'User wallet details retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('User not found', 404);
        }
    }

    /**
     * Manually credit a wallet
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function creditWallet(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'wallet_type' => 'required|in:earning,bonus,reward,holding,commission',
                'amount' => 'required|numeric|min:0.01',
                'description' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            /** @var \App\Models\User $admin */
            $admin = Auth::user();

            $result = $this->adminWalletService->creditWallet(
                $request->user_id,
                $request->wallet_type,
                $request->amount,
                $request->description,
                $admin->id
            );

            if ($result['success']) {
                return $this->successResponse($result['transaction'], $result['message']);
            } else {
                return $this->errorResponse($result['message'], 422);
            }
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to credit wallet: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Manually debit a wallet
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function debitWallet(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'wallet_type' => 'required|in:earning,bonus,reward,holding,commission',
                'amount' => 'required|numeric|min:0.01',
                'description' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            /** @var \App\Models\User $admin */
            $admin = Auth::user();

            $result = $this->adminWalletService->debitWallet(
                $request->user_id,
                $request->wallet_type,
                $request->amount,
                $request->description,
                $admin->id
            );

            if ($result['success']) {
                return $this->successResponse($result['transaction'], $result['message']);
            } else {
                return $this->errorResponse($result['message'], 422);
            }
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to debit wallet: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Process withdrawal (approve/reject)
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function processWithdrawal(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:approved,rejected,processing,completed,failed,cancelled',
                'admin_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            /** @var \App\Models\User $admin */
            $admin = Auth::user();

            $result = $this->adminWalletService->processWithdrawal(
                $id,
                $request->status,
                $admin->id,
                $request->admin_notes
            );

            if ($result['success']) {
                return $this->successResponse($result['withdrawal'], $result['message']);
            } else {
                return $this->errorResponse($result['message'], 422);
            }
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process withdrawal: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export wallet transactions to CSV
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function exportTransactions(Request $request)
    {
        try {
            $filters = $request->only([
                'user_id',
                'wallet_id',
                'type',
                'category',
                'status',
                'from_date',
                'to_date',
                'search'
            ]);

            $result = $this->adminWalletService->getAllWalletTransactions($filters);
            $transactions = $result['transactions'];

            $csvData = [];
            $csvData[] = [
                'Date',
                'User Name',
                'User Email',
                'Wallet Type',
                'Transaction Type',
                'Category',
                'Amount',
                'Balance Before',
                'Balance After',
                'Reference ID',
                'Description',
                'Status'
            ];

            foreach ($transactions as $transaction) {
                $csvData[] = [
                    $transaction['created_at'],
                    $transaction['user_name'],
                    $transaction['user_email'],
                    $transaction['wallet_display_name'],
                    $transaction['type'],
                    $transaction['category'],
                    $transaction['amount'],
                    $transaction['balance_before'],
                    $transaction['balance_after'],
                    $transaction['reference_id'],
                    $transaction['description'],
                    $transaction['status']
                ];
            }

            $filename = 'admin_wallet_transactions_' . now()->format('Y-m-d_H-i-s') . '.csv';

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
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to export transactions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export withdrawals to CSV
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function exportWithdrawals(Request $request)
    {
        try {
            $filters = $request->only([
                'user_id',
                'status',
                'method',
                'from_date',
                'to_date',
                'search'
            ]);

            $result = $this->adminWalletService->getAllWithdrawals($filters);
            $withdrawals = $result['withdrawals'];

            $csvData = [];
            $csvData[] = [
                'Date',
                'User Name',
                'User Email',
                'Withdrawal ID',
                'Amount',
                'Fee',
                'Net Amount',
                'Method',
                'Status',
                'User Notes',
                'Admin Notes',
                'Processed By',
                'Processed At'
            ];

            foreach ($withdrawals as $withdrawal) {
                $csvData[] = [
                    $withdrawal['created_at'],
                    $withdrawal['user_name'],
                    $withdrawal['user_email'],
                    $withdrawal['withdrawal_id'],
                    $withdrawal['amount'],
                    $withdrawal['fee'],
                    $withdrawal['net_amount'],
                    $withdrawal['method_display_name'],
                    $withdrawal['status_display_name'],
                    $withdrawal['user_notes'],
                    $withdrawal['admin_notes'],
                    $withdrawal['processed_by'],
                    $withdrawal['processed_at']
                ];
            }

            $filename = 'admin_withdrawals_' . now()->format('Y-m-d_H-i-s') . '.csv';

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
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to export withdrawals: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get wallet dashboard data
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboard()
    {
        try {
            $stats = $this->adminWalletService->getWalletStatistics();

            // Extract key metrics for dashboard
            $dashboard = [
                'overview' => $stats['overview'],
                'recent_activity' => $stats['recent_activity'],
                'top_users' => $stats['top_users_by_balance'],
                'daily_stats' => $stats['daily_stats'],
                'withdrawal_summary' => [
                    'pending' => $stats['by_withdrawal_status']['pending'] ?? null,
                    'completed' => $stats['by_withdrawal_status']['completed'] ?? null,
                    'failed' => $stats['by_withdrawal_status']['failed'] ?? null
                ]
            ];

            return $this->successResponse($dashboard, 'Dashboard data retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve dashboard data: ' . $e->getMessage(), 500);
        }
    }
}

