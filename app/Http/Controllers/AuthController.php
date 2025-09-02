<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;


class AuthController extends Controller
{
    use ApiResponseTrait;

    public function adminLogin(Request $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return $this->errorResponse('Invalid login credentials', 401);
        }

        $user = Auth::user();
        if (!$user->hasRole('admin')) {
            Auth::logout();
            return $this->errorResponse('Access denied. Admin privileges required.', 403);
        }

        $token = $user->createToken('admin-auth')->plainTextToken;

        return $this->successResponse(['token' => $token, 'user' => $user], 'Admin login successful');
    }

    public function customerLogin(Request $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return $this->errorResponse('Invalid login credentials', 401);
        }

        $user = Auth::user();
        if (!$user->hasRole('customer')) {
            Auth::logout();
            return $this->errorResponse('Access denied. Customer account required.', 403);
        }

        $token = $user->createToken('customer-auth')->plainTextToken;

        return $this->successResponse(['token' => $token, 'user' => $user], 'Customer login successful');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|string|email|unique:users',
            'password'    => 'required|string|min:6',
            'referral_code' => 'required|exists:users,referral_code'
        ]);

        $sponsor = User::where('referral_code', $request->referral_code)->first();

        // Check sponsor directs limit
        if ($sponsor->directs()->count() >= 4) {
            return $this->errorResponse('This referral code already has 4 directs.', 422);
        }

        // Check sponsor has package
        if (!$sponsor->package_id) {
            return $this->errorResponse(
                'This sponsor has not purchased any package. You cannot register under this referral code.',
                422
            );
        }

        // Create user
        $user = User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'sponsor_id' => $sponsor->id,
        ]);
        $user->assignRole('customer');

        // Generate Sanctum token
        $token = $user->createToken('auth')->plainTextToken;

        // Return using trait
        return $this->successResponse(
            ['token' => $token, 'user' => $user],
            'User registered successfully'
        );
    }
}
