<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId)
            ],
            'password' => 'sometimes|string|min:6',
            'sponsor_id' => 'nullable|integer|exists:users,id',
            'package_id' => 'nullable|integer|exists:packages,id',
            'roles' => 'sometimes|array',
            'roles.*' => 'string|in:admin,customer'
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'Email must be a valid email address',
            'email.unique' => 'Email already exists',
            'password.min' => 'Password must be at least 6 characters',
            'sponsor_id.exists' => 'Selected sponsor does not exist',
            'package_id.exists' => 'Selected package does not exist',
            'roles.array' => 'Roles must be an array',
            'roles.*.in' => 'Invalid role. Must be admin or customer'
        ];
    }
}
