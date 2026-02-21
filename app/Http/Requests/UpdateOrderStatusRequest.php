<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_status' => 'required|in:pending,processing,shipped,delivered,cancelled',
            'cancellation_reason' => 'required_if:order_status,cancelled|nullable|string|max:500',
            // Shipping details validation
            'courier_name' => 'required_if:order_status,shipped|nullable|string|max:255',
            'courier_phone' => 'nullable|string|max:20',
            'tracking_number' => 'required_if:order_status,shipped|nullable|string|max:255',
            'estimated_delivery_at' => 'nullable|date',
            'shipping_notes' => 'nullable|string|max:500',
        ];
    }

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
            ? 'There are multiple validation errors. Please review the form.'
            : 'Form validation failed.';

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $fieldErrors,
        ], 422));
    }
}
