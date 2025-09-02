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
            'name'       => 'required|string|max:255',
            'email'      => 'required|string|email|unique:users',
            'password'   => 'required|string|min:6',
            'sponsor_id' => 'required|exists:users,id',
        ]);

        // Check sponsor capacity (max 4 directs)
        $sponsor = User::find($request->sponsor_id);
        if ($sponsor->directs()->count() >= 4) {
            return $this->errorResponse('This sponsor already has 4 direct members. Please use another sponsor ID.', 422);
        }

        // Create new user
        $user = User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'sponsor_id' => $request->sponsor_id,
            'package_id' => $request->package_id,
        ]);

        $user->assignRole('customer');

        return $this->successResponse($user, 'User registered successfully');
    }
}
