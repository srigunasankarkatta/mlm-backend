<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Services\Admin\UserService;
use App\DTOs\Admin\CreateUserDTO;
use App\DTOs\Admin\UpdateUserDTO;
use App\Traits\ApiResponseTrait;
use App\Models\Transaction;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private UserService $userService
    ) {}

    /**
     * Display a listing of users
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $filters = $request->only(['role', 'package_id', 'search']);

            $users = $this->userService->getAllUsers($perPage, $filters);

            $formattedUsers = $users->getCollection()->map(function ($user) {
                return $user->toArray();
            });

            return $this->successResponse([
                'users' => $formattedUsers,
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'has_more_pages' => $users->hasMorePages()
                ]
            ], 'Users fetched successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch users: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created user
     */
    public function store(CreateUserRequest $request)
    {
        try {
            $dto = CreateUserDTO::fromArray($request->validated());
            $user = $this->userService->createUser($dto);

            return $this->successResponse(
                $user->toArray(),
                'User created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified user
     */
    public function show(int $id)
    {
        try {
            $user = $this->userService->getUserById($id);

            if (!$user) {
                return $this->errorResponse('User not found', 404);
            }

            return $this->successResponse(
                $user->toArray(),
                'User fetched successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified user
     */
    public function update(UpdateUserRequest $request, int $id)
    {
        try {
            $dto = UpdateUserDTO::fromArray($request->validated());
            $user = $this->userService->updateUser($id, $dto);

            if (!$user) {
                return $this->errorResponse('User not found', 404);
            }

            return $this->successResponse(
                $user->toArray(),
                'User updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified user
     */
    public function destroy(int $id)
    {
        try {
            $deleted = $this->userService->deleteUser($id);

            if (!$deleted) {
                return $this->errorResponse('User not found', 404);
            }

            return $this->successResponse(
                null,
                'User deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's MLM tree
     */
    public function tree(int $id, Request $request)
    {
        try {
            $maxLevel = $request->get('max_level', 10);
            $tree = $this->userService->getUserTree($id, $maxLevel);

            if (!$tree) {
                return $this->errorResponse('User not found', 404);
            }

            return $this->successResponse(
                $tree,
                'User tree fetched successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch user tree: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's income history
     */
    public function incomes(int $id, Request $request)
    {
        try {
            $perPage = $request->get('per_page', 20);
            $incomes = $this->userService->getUserIncomes($id, $perPage);

            if (!$incomes) {
                return $this->errorResponse('User not found', 404);
            }

            $formattedIncomes = $incomes->getCollection()->map(function ($income) {
                return [
                    'id' => $income->id,
                    'type' => $income->type,
                    'amount' => number_format($income->amount, 2),
                    'remark' => $income->remark,
                    'date' => $income->created_at->format('Y-m-d H:i:s'),
                    'formatted_date' => $income->created_at->format('M d, Y'),
                    'time' => $income->created_at->format('h:i A')
                ];
            });

            return $this->successResponse([
                'incomes' => $formattedIncomes,
                'pagination' => [
                    'current_page' => $incomes->currentPage(),
                    'last_page' => $incomes->lastPage(),
                    'per_page' => $incomes->perPage(),
                    'total' => $incomes->total(),
                    'has_more_pages' => $incomes->hasMorePages()
                ]
            ], 'User incomes fetched successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch user incomes: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user's transaction history
     */
    public function transactions(int $id, Request $request)
    {
        try {
            $user = $this->userService->getUserById($id);

            if (!$user) {
                return $this->errorResponse('User not found', 404);
            }

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

            $formattedTransactions = $transactions->getCollection()->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'transaction_id' => $transaction->transaction_id,
                    'type' => $transaction->type,
                    'status' => $transaction->status,
                    'amount' => number_format($transaction->amount, 2),
                    'payment_method' => $transaction->payment_method,
                    'description' => $transaction->description,
                    'package' => $transaction->package ? [
                        'id' => $transaction->package->id,
                        'name' => $transaction->package->name,
                        'price' => $transaction->package->price,
                    ] : null,
                    'created_at' => $transaction->created_at->format('Y-m-d H:i:s'),
                    'formatted_date' => $transaction->created_at->format('M d, Y'),
                    'time' => $transaction->created_at->format('h:i A')
                ];
            });

            return $this->successResponse([
                'transactions' => $formattedTransactions,
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'has_more_pages' => $transactions->hasMorePages()
                ]
            ], 'User transactions fetched successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch user transactions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user statistics
     */
    public function stats()
    {
        try {
            $stats = $this->userService->getUserStats();

            return $this->successResponse(
                $stats,
                'User statistics fetched successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch user statistics: ' . $e->getMessage(), 500);
        }
    }
}
