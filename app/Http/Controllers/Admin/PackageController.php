<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreatePackageRequest;
use App\Http\Requests\Admin\UpdatePackageRequest;
use App\Services\Admin\PackageService;
use App\DTOs\Admin\CreatePackageDTO;
use App\DTOs\Admin\UpdatePackageDTO;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private PackageService $packageService
    ) {}

    /**
     * Display a listing of packages
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $packages = $this->packageService->getAllPackages($perPage);

            $formattedPackages = $packages->getCollection()->map(function ($package) {
                return $package->toArray();
            });

            return $this->successResponse([
                'packages' => $formattedPackages,
                'pagination' => [
                    'current_page' => $packages->currentPage(),
                    'last_page' => $packages->lastPage(),
                    'per_page' => $packages->perPage(),
                    'total' => $packages->total(),
                    'has_more_pages' => $packages->hasMorePages()
                ]
            ], 'Packages fetched successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch packages: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created package
     */
    public function store(CreatePackageRequest $request)
    {
        try {
            $dto = CreatePackageDTO::fromArray($request->validated());
            $package = $this->packageService->createPackage($dto);

            return $this->successResponse(
                $package->toArray(),
                'Package created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create package: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified package
     */
    public function show(int $id)
    {
        try {
            $package = $this->packageService->getPackageById($id);

            if (!$package) {
                return $this->errorResponse('Package not found', 404);
            }

            return $this->successResponse(
                $package->toArray(),
                'Package fetched successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch package: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified package
     */
    public function update(UpdatePackageRequest $request, int $id)
    {
        try {
            $dto = UpdatePackageDTO::fromArray($request->validated());
            $package = $this->packageService->updatePackage($id, $dto);

            if (!$package) {
                return $this->errorResponse('Package not found', 404);
            }

            return $this->successResponse(
                $package->toArray(),
                'Package updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update package: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified package
     */
    public function destroy(int $id)
    {
        try {
            $deleted = $this->packageService->deletePackage($id);

            if (!$deleted) {
                return $this->errorResponse('Package not found', 404);
            }

            return $this->successResponse(
                null,
                'Package deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete package: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get package statistics
     */
    public function stats()
    {
        try {
            $stats = $this->packageService->getPackageStats();

            return $this->successResponse(
                $stats,
                'Package statistics fetched successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch package statistics: ' . $e->getMessage(), 500);
        }
    }
}
