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

        $user = Auth::user();
        $package = Package::findOrFail($request->package_id);

        // Assign package to user
        $user->package_id = $package->id;
        $user->save();

        // Trigger income distribution
        app(IncomeController::class)->distribute($user);

        return $this->successResponse([
            'user' => $user,
            'package' => $package
        ], 'Package purchased successfully and incomes distributed');
    }
}
