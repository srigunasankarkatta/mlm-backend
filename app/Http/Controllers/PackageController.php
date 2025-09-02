<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Package;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PackageController extends Controller
{
    use ApiResponseTrait;

    /**
     * List all available packages/plans
     */
    public function index()
    {
        $packages = Package::all();

        if ($packages->isEmpty()) {
            return $this->errorResponse('No packages available', 404);
        }

        return $this->successResponse($packages, 'Packages fetched successfully');
    }

    public function purchase(Request $request)
    {
        $request->validate([
            'package_id' => 'required|exists:packages,id',
        ]);

        $user = auth()->user();
        $package = Package::findOrFail($request->package_id);

        // Prevent duplicate purchase
        if ($user->package_id == $package->id) {
            return $this->errorResponse('You already own this package.', 422);
        }

        // ✅ Enforce sequential plan purchase
        if ($package->id > 1) {
            if ($user->package_id !== ($package->id - 1)) {
                return $this->errorResponse("You must purchase Package-" . ($package->id - 1) . " before upgrading to this plan.", 422);
            }
        }

        // Assign package to user
        $user->package_id = $package->id;
        $user->save();

        // Trigger income distribution
        app(\App\Http\Controllers\IncomeController::class)->distribute($user);

        return $this->successResponse([
            'user_id' => $user->id,
            'package' => [
                'id' => $package->id,
                'name' => $package->name,
                'price' => $package->price,
                'level_unlock' => $package->level_unlock,
            ]
        ], 'Package purchased successfully and incomes distributed');
    }
}
