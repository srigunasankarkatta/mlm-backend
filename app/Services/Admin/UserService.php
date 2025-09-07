<?php

namespace App\Services\Admin;

use App\Models\User;
use App\DTOs\Admin\UserDTO;
use App\DTOs\Admin\CreateUserDTO;
use App\DTOs\Admin\UpdateUserDTO;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function getAllUsers(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = User::with(['package', 'sponsor', 'roles'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['role'])) {
            $query->whereHas('roles', function ($q) use ($filters) {
                $q->where('name', $filters['role']);
            });
        }

        if (isset($filters['package_id'])) {
            $query->where('package_id', $filters['package_id']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('referral_code', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($perPage);

        $users->getCollection()->transform(function ($user) {
            return UserDTO::fromModel($user);
        });

        return $users;
    }

    public function getUserById(int $id): ?UserDTO
    {
        $user = User::with(['package', 'sponsor', 'roles'])->find($id);

        return $user ? UserDTO::fromModel($user) : null;
    }

    public function createUser(CreateUserDTO $dto): UserDTO
    {
        $userData = $dto->toArray();
        $userData['password'] = Hash::make($userData['password']);

        $user = User::create($userData);

        // Assign roles
        if (isset($dto->roles)) {
            $user->assignRole($dto->roles);
        }

        return UserDTO::fromModel($user->load(['package', 'sponsor', 'roles']));
    }

    public function updateUser(int $id, UpdateUserDTO $dto): ?UserDTO
    {
        $user = User::find($id);

        if (!$user) {
            return null;
        }

        $updateData = $dto->toArray();

        // Hash password if provided
        if (isset($updateData['password'])) {
            $updateData['password'] = Hash::make($updateData['password']);
        }

        $user->update($updateData);

        // Update roles if provided
        if (isset($dto->roles)) {
            $user->syncRoles($dto->roles);
        }

        return UserDTO::fromModel($user->fresh(['package', 'sponsor', 'roles']));
    }

    public function deleteUser(int $id): bool
    {
        $user = User::find($id);

        if (!$user) {
            return false;
        }

        // Check if user has directs
        if ($user->directs()->count() > 0) {
            throw new \Exception('Cannot delete user who has directs. Please reassign directs first.');
        }

        return $user->delete();
    }

    public function getUserTree(int $userId, int $maxLevel = 10): ?array
    {
        $user = User::find($userId);

        if (!$user) {
            return null;
        }

        return $this->buildTree($user, 1, $maxLevel);
    }

    public function getUserIncomes(int $userId, int $perPage = 20): ?LengthAwarePaginator
    {
        $user = User::find($userId);

        if (!$user) {
            return null;
        }

        return $user->incomes()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getUserStats(): array
    {
        $totalUsers = User::count();
        $adminUsers = User::role('admin')->count();
        $customerUsers = User::role('customer')->count();
        $usersWithPackages = User::whereNotNull('package_id')->count();
        $usersWithoutPackages = User::whereNull('package_id')->count();

        $packageDistribution = User::selectRaw('package_id, COUNT(*) as count')
            ->whereNotNull('package_id')
            ->groupBy('package_id')
            ->with('package:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'package_id' => $item->package_id,
                    'package_name' => $item->package?->name,
                    'count' => $item->count
                ];
            });

        return [
            'total_users' => $totalUsers,
            'admin_users' => $adminUsers,
            'customer_users' => $customerUsers,
            'users_with_packages' => $usersWithPackages,
            'users_without_packages' => $usersWithoutPackages,
            'package_distribution' => $packageDistribution
        ];
    }

    private function buildTree(User $user, int $currentLevel, int $maxLevel): array
    {
        if ($currentLevel > $maxLevel) {
            return [];
        }

        $children = $user->directs()->take(4)->get();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'referral_code' => $user->referral_code,
            'package' => $user->package ? $user->package->name : null,
            'level' => $currentLevel,
            'directs_count' => $children->count(),
            'children' => $children->map(function ($child) use ($currentLevel, $maxLevel) {
                return $this->buildTree($child, $currentLevel + 1, $maxLevel);
            })
        ];
    }
}
