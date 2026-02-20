<?php

namespace App\Http\Requests;

use App\Traits\FileUploadTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ConfirmCheckoutRequest extends FormRequest
{
    use FileUploadTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => 'required|in:card,cash_on_delivery,bank_transfer',
            'payment_slip' => 'required_if:payment_method,bank_transfer|image|mimes:jpeg,png,jpg,pdf|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method.required' => 'Please select a payment method',
            'payment_method.in' => 'Invalid payment method selected',
            'payment_slip.required_if' => 'Payment slip is required for bank transfer payments',
            'payment_slip.image' => 'Payment slip must be an image',
            'payment_slip.max' => 'Payment slip size must not exceed 5MB',
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
            ? 'There are multiple validation errors. Please review the form and correct the issues.'
            : 'There is an issue with the input for ' . $fieldErrors->first()['field'] . '.';

        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'success' => false,
            'message' => $message,
            'errors' => $fieldErrors,
        ], 422));
    }
}
