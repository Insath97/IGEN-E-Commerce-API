<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = auth('api')->user();

        return [
            // User fields
            'name' => 'sometimes|required|string|max:255',
            'username' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,webp',
            
            // Password update
            'current_password' => [
                'nullable', 
                'required_with:password', 
                'string'
            ],
            'password' => 'nullable|string|min:8|confirmed',
            'password_confirmation' => 'nullable|string|min:8',

            // Customer fields
            'phone' => 'sometimes|nullable|string|max:20',
            'have_whatsapp' => 'sometimes|boolean',
            'whatsapp_number' => 'sometimes|nullable|string|max:20',
            'address_line_1' => 'sometimes|nullable|string|max:255',
            'address_line_2' => 'sometimes|nullable|string|max:255',
            'landmark' => 'sometimes|nullable|string|max:255',
            'city' => 'sometimes|nullable|string|max:100',
            'state' => 'sometimes|nullable|string|max:100',
            'country' => 'sometimes|nullable|string|max:100',
            'postal_code' => 'sometimes|nullable|string|max:20',
        ];
    }
}
