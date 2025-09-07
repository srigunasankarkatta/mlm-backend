<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\Customer\CustomerWalletService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CustomerWalletController extends Controller
{
    use ApiResponseTrait;

    protected $customerWalletService;

    public function __construct(CustomerWalletService $customerWalletService)
    {
        $this->customerWalletService = $customerWalletService;
    }

    /**
     * Get customer's wallet balances
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBalances()
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            $balances = $this->customerWalletService->getCustomerWalletBalances($user->id);
            
            return $this->successResponse([
                'wallets' => $balances,
                'total_balance' => array_sum(array_column($balances, 'balance')),
                'total_available' => array_sum(array_column($balances, 'available_balance'))
            ], 'Wallet balances retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve wallet balances: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get customer's wallet transaction history
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTransactions(Request $request)
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            $filters = $request->only([
                'wallet_type', 'type', 'category', 'status', 
                'from_date', 'to_date', 'search', 'per_page'
            ]);
            
            $result = $this->customerWalletService->getCustomerTransactions($user->id, $filters);
            
            return $this->successResponse($result, 'Wallet transactions retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve transactions: ' . $e->getMessage(), 500);
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
            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            $transaction = $user->walletTransactions()
                ->with(['wallet'])
                ->findOrFail($id);
            
            $formattedTransaction = $this->customerWalletService->formatTransaction($transaction);
            
            return $this->successResponse($formattedTransaction, 'Transaction details retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Transaction not found', 404);
        }
    }

    /**
     * Get customer's wallet summary and analytics
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSummary()
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            $summary = $this->customerWalletService->getCustomerWalletSummary($user->id);
            
            return $this->successResponse($summary, 'Wallet summary retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve wallet summary: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Request withdrawal from wallet
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestWithdrawal(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'wallet_type' => 'required|in:earning,bonus,commission',
                'amount' => 'required|numeric|min:10',
                'method' => 'required|in:bank_transfer,digital_wallet,cryptocurrency,check,cash_pickup',
                'payment_details' => 'required|array',
                'payment_details.account_name' => 'required|string|max:255',
                'payment_details.account_number' => 'required|string|max:255',
                'payment_details.bank_name' => 'required_if:method,bank_transfer|string|max:255',
                'payment_details.routing_number' => 'required_if:method,bank_transfer|string|max:255',
                'payment_details.email' => 'required_if:method,digital_wallet|email|max:255',
                'payment_details.wallet_address' => 'required_if:method,cryptocurrency|string|max:255',
                'payment_details.pickup_location' => 'required_if:method,cash_pickup|string|max:255',
                'notes' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            $result = $this->customerWalletService->createWithdrawalRequest($user->id, $request->all());
            
            if ($result['success']) {
                return $this->successResponse($result['withdrawal'], $result['message']);
            } else {
                return $this->errorResponse($result['message'], 422);
            }
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process withdrawal request: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get customer's withdrawal history
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWithdrawals(Request $request)
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            $filters = $request->only([
                'status', 'method', 'from_date', 'to_date', 'search', 'per_page'
            ]);
            
            $result = $this->customerWalletService->getCustomerWithdrawals($user->id, $filters);
            
            return $this->successResponse($result, 'Withdrawal history retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve withdrawals: ' . $e->getMessage(), 500);
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
            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            $withdrawal = $user->withdrawals()
                ->with(['wallet', 'processedBy'])
                ->findOrFail($id);
            
            $formattedWithdrawal = $this->customerWalletService->formatWithdrawal($withdrawal);
            
            return $this->successResponse($formattedWithdrawal, 'Withdrawal details retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Withdrawal not found', 404);
        }
    }

    /**
     * Get withdrawal limits and available methods
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWithdrawalLimits()
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            $limits = $this->customerWalletService->getWithdrawalLimits($user->id);
            
            return $this->successResponse([
                'limits' => $limits,
                'global_limits' => [
                    'min_withdrawal' => '10.00',
                    'max_withdrawal' => '5000.00',
                    'daily_limit' => '500.00',
                    'monthly_limit' => '5000.00',
                    'processing_time' => '1-3 business days'
                ]
            ], 'Withdrawal limits retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve withdrawal limits: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get wallet statistics for dashboard
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboardStats()
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            $summary = $this->customerWalletService->getCustomerWalletSummary($user->id);
            
            // Extract key stats for dashboard
            $dashboardStats = [
                'total_balance' => $summary['overview']['total_balance'],
                'total_earned' => $summary['overview']['total_earned'],
                'total_spent' => $summary['overview']['total_spent'],
                'net_earnings' => $summary['overview']['net_earnings'],
                'pending_withdrawals' => $summary['overview']['pending_withdrawals'],
                'wallet_count' => count($summary['wallet_breakdown']),
                'recent_transactions' => array_slice($summary['recent_activity'], 0, 3),
                'withdrawal_stats' => $summary['withdrawal_stats']
            ];
            
            return $this->successResponse($dashboardStats, 'Dashboard statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve dashboard statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get monthly earnings chart data
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMonthlyEarnings()
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            $summary = $this->customerWalletService->getCustomerWalletSummary($user->id);
            
            return $this->successResponse([
                'monthly_data' => $summary['monthly_stats'],
                'income_breakdown' => $summary['income_breakdown'],
                'expense_breakdown' => $summary['expense_breakdown']
            ], 'Monthly earnings data retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve monthly earnings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get wallet activity feed
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActivityFeed(Request $request)
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            $limit = $request->get('limit', 10);
            
            // Get recent transactions
            $transactions = $user->walletTransactions()
                ->with(['wallet'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($transaction) {
                    return $this->customerWalletService->formatTransaction($transaction);
                });
            
            // Get recent withdrawals
            $withdrawals = $user->withdrawals()
                ->with(['wallet'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($withdrawal) {
                    return $this->customerWalletService->formatWithdrawal($withdrawal);
                });
            
            // Combine and sort by date
            $activity = collect()
                ->merge($transactions->map(function ($item) {
                    $item['activity_type'] = 'transaction';
                    return $item;
                }))
                ->merge($withdrawals->map(function ($item) {
                    $item['activity_type'] = 'withdrawal';
                    return $item;
                }))
                ->sortByDesc('iso_date')
                ->take($limit)
                ->values();
            
            return $this->successResponse([
                'activities' => $activity,
                'total_count' => $activity->count()
            ], 'Activity feed retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve activity feed: ' . $e->getMessage(), 500);
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
            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            $filters = $request->only([
                'wallet_type', 'type', 'category', 'status', 
                'from_date', 'to_date', 'search'
            ]);
            
            $result = $this->customerWalletService->getCustomerTransactions($user->id, $filters);
            $transactions = $result['transactions'];
            
            $csvData = [];
            $csvData[] = [
                'Date',
                'Reference ID',
                'Type',
                'Category',
                'Amount',
                'Balance Before',
                'Balance After',
                'Description',
                'Status',
                'Wallet Type'
            ];
            
            foreach ($transactions as $transaction) {
                $csvData[] = [
                    $transaction['formatted_date'] . ' ' . $transaction['formatted_time'],
                    $transaction['reference_id'],
                    $transaction['type'],
                    $transaction['category'],
                    $transaction['amount'],
                    $transaction['balance_before'],
                    $transaction['balance_after'],
                    $transaction['description'],
                    $transaction['status'],
                    $transaction['wallet_display_name']
                ];
            }
            
            $filename = 'wallet_transactions_' . $user->id . '_' . now()->format('Y-m-d_H-i-s') . '.csv';
            
            $callback = function() use ($csvData) {
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
}


