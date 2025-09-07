<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $packageId = $this->route('package');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('packages', 'name')->ignore($packageId)
            ],
            'price' => 'sometimes|numeric|min:0.01',
            'level_unlock' => 'sometimes|integer|min:1|max:10',
            'description' => 'nullable|string|max:1000'
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Package name already exists',
            'price.numeric' => 'Package price must be a number',
            'price.min' => 'Package price must be at least 0.01',
            'level_unlock.integer' => 'Level unlock must be an integer',
            'level_unlock.min' => 'Level unlock must be at least 1',
            'level_unlock.max' => 'Level unlock cannot exceed 10',
            'description.max' => 'Description cannot exceed 1000 characters'
        ];
    }
}
