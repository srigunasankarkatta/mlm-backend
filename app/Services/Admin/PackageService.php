<?php

namespace App\Services\Admin;

use App\Models\Package;
use App\DTOs\Admin\PackageDTO;
use App\DTOs\Admin\CreatePackageDTO;
use App\DTOs\Admin\UpdatePackageDTO;
use Illuminate\Pagination\LengthAwarePaginator;

class PackageService
{
    public function getAllPackages(int $perPage = 15): LengthAwarePaginator
    {
        $packages = Package::orderBy('created_at', 'desc')
            ->paginate($perPage);

        $packages->getCollection()->transform(function ($package) {
            return PackageDTO::fromModel($package);
        });

        return $packages;
    }

    public function getPackageById(int $id): ?PackageDTO
    {
        $package = Package::find($id);

        return $package ? PackageDTO::fromModel($package) : null;
    }

    public function createPackage(CreatePackageDTO $dto): PackageDTO
    {
        $package = Package::create($dto->toArray());

        return PackageDTO::fromModel($package);
    }

    public function updatePackage(int $id, UpdatePackageDTO $dto): ?PackageDTO
    {
        $package = Package::find($id);

        if (!$package) {
            return null;
        }

        $package->update($dto->toArray());

        return PackageDTO::fromModel($package->fresh());
    }

    public function deletePackage(int $id): bool
    {
        $package = Package::find($id);

        if (!$package) {
            return false;
        }

        // Check if package is being used by any users
        if ($package->users()->count() > 0) {
            throw new \Exception('Cannot delete package that is being used by users');
        }

        return $package->delete();
    }

    public function getPackageStats(): array
    {
        $totalPackages = Package::count();
        $totalUsers = Package::withCount('users')->get()->sum('users_count');

        $packageUsage = Package::withCount('users')
            ->orderBy('users_count', 'desc')
            ->get()
            ->map(function ($package) {
                return [
                    'id' => $package->id,
                    'name' => $package->name,
                    'users_count' => $package->users_count
                ];
            });

        return [
            'total_packages' => $totalPackages,
            'total_users_with_packages' => $totalUsers,
            'package_usage' => $packageUsage
        ];
    }
}
