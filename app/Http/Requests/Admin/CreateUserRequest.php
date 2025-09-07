<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'sponsor_id' => 'nullable|integer|exists:users,id',
            'package_id' => 'nullable|integer|exists:packages,id',
            'roles' => 'sometimes|array',
            'roles.*' => 'string|in:admin,customer'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required',
            'email.required' => 'Email is required',
            'email.email' => 'Email must be a valid email address',
            'email.unique' => 'Email already exists',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 6 characters',
            'sponsor_id.exists' => 'Selected sponsor does not exist',
            'package_id.exists' => 'Selected package does not exist',
            'roles.array' => 'Roles must be an array',
            'roles.*.in' => 'Invalid role. Must be admin or customer'
        ];
    }
}
