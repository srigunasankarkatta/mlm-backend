<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Package;
use App\Models\Transaction;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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
            'payment_method' => 'nullable|in:cash,bank_transfer,credit_card,digital_wallet',
            'description' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $package = Package::findOrFail($request->package_id);

        // Prevent duplicate purchase
        if ($user->package_id == $package->id) {
            return $this->errorResponse('You already own this package.', 422);
        }

        // ✅ Enforce sequential plan purchase based on level_unlock
        if ($package->level_unlock > 1) {
            $requiredLevel = $package->level_unlock - 1;
            $requiredPackage = Package::where('level_unlock', $requiredLevel)->first();

            if (!$user->package_id) {
                return $this->errorResponse("You must purchase {$requiredPackage->name} before upgrading to this plan.", 422);
            }

            $userPackage = Package::find($user->package_id);
            if (!$userPackage || $userPackage->level_unlock !== $requiredLevel) {
                return $this->errorResponse("You must purchase {$requiredPackage->name} before upgrading to this plan.", 422);
            }
        }

        // Create transaction record
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'type' => Transaction::TYPE_PURCHASE,
            'status' => Transaction::STATUS_COMPLETED,
            'payment_method' => $request->payment_method ?? Transaction::PAYMENT_CASH,
            'transaction_id' => 'TXN-' . strtoupper(Str::random(10)),
            'description' => $request->description ?? "Package purchase: {$package->name}",
            'metadata' => [
                'package_name' => $package->name,
                'level_unlock' => $package->level_unlock,
                'previous_package_id' => $user->package_id,
                'purchase_date' => now()->toISOString(),
            ]
        ]);

        // Assign package to user
        $user->package_id = $package->id;
        $user->save();

        // Trigger income distribution
        app(\App\Http\Controllers\IncomeController::class)->distribute($user);

        return $this->successResponse([
            'user_id' => $user->id,
            'transaction' => [
                'id' => $transaction->id,
                'transaction_id' => $transaction->transaction_id,
                'amount' => $transaction->amount,
                'status' => $transaction->status,
                'payment_method' => $transaction->payment_method,
                'created_at' => $transaction->created_at,
            ],
            'package' => [
                'id' => $package->id,
                'name' => $package->name,
                'price' => $package->price,
                'level_unlock' => $package->level_unlock,
            ]
        ], 'Package purchased successfully and incomes distributed');
    }
}
