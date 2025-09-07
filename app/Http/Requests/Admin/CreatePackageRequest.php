<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreatePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:packages,name',
            'price' => 'required|numeric|min:0.01',
            'level_unlock' => 'required|integer|min:1|max:10',
            'description' => 'nullable|string|max:1000'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Package name is required',
            'name.unique' => 'Package name already exists',
            'price.required' => 'Package price is required',
            'price.numeric' => 'Package price must be a number',
            'price.min' => 'Package price must be at least 0.01',
            'level_unlock.required' => 'Level unlock is required',
            'level_unlock.integer' => 'Level unlock must be an integer',
            'level_unlock.min' => 'Level unlock must be at least 1',
            'level_unlock.max' => 'Level unlock cannot exceed 10',
            'description.max' => 'Description cannot exceed 1000 characters'
        ];
    }
}
