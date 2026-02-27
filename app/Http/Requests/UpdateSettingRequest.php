<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // General settings
            'site_name' => 'nullable|string|max:255',
            'site_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'site_favicon' => 'nullable|image|mimes:ico,png|max:1024',
            'footer_text' => 'nullable|string',
            
            // Shop settings
            'shop_email' => 'nullable|email|max:255',
            'shop_phone' => 'nullable|string|max:50',
            'shop_address' => 'nullable|string|max:500',
            'shop_currency' => 'nullable|string|max:10',
            
            // Admin settings
            'admin_dashboard_title' => 'nullable|string|max:255',
            'admin_items_per_page' => 'nullable|integer|min:1|max:100',
            
            // Customer settings
            'customer_support_email' => 'nullable|email|max:255',
            'customer_terms_link' => 'nullable|string|max:255',
            
            // API settings
            'mail_host' => 'nullable|string|max:255',
            'stripe_key' => 'nullable|string|max:255',
            'firebase_secret' => 'nullable|string|max:255',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        $errorMessages = $validator->errors();

        $fieldErrors = collect($errorMessages->getMessages())->map(function ($messages, $field) {
            return [
                'field' => $field,
                'messages' => $messages,
            ];
        })->values();

        $message = $fieldErrors->count() > 1
            ? 'There are multiple validation errors. Please review the form and correct the issues.'
            : 'There is an issue with the input for ' . $fieldErrors->first()['field'] . '.';

        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $fieldErrors,
        ], 422));
    }
}
