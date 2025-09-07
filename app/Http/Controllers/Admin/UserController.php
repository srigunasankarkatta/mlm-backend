<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Services\Admin\UserService;
use App\DTOs\Admin\CreateUserDTO;
use App\DTOs\Admin\UpdateUserDTO;
use App\Traits\ApiResponseTrait;
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
