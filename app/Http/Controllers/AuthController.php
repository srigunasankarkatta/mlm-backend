<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;

class AuthController extends Controller {
    use ApiResponseTrait;

    public function register(Request $request) {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'sponsor_id' => $request->sponsor_id,
            'package_id' => $request->package_id,
        ]);
        $user->assignRole('customer');

        return $this->successResponse($user, 'User registered successfully');
    }

    public function login(Request $request) {
        if (!Auth::attempt($request->only('email','password'))) {
            return $this->errorResponse('Invalid login credentials', 401);
        }
        $token = $request->user()->createToken('auth')->plainTextToken;

        return $this->successResponse(['token' => $token], 'Login successful');
    }
}
