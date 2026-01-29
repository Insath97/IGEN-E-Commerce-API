<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateCouponRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $couponId = $this->route('coupon');

        return [
            'code' => 'nullable|string|max:255|unique:coupons,code,' . $couponId,
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'type' => 'nullable|in:percentage,fixed_amount,tiered_percentage',
            'value' => 'nullable|numeric|min:0',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:start_date',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'tiers' => 'nullable|array',
            'tiers.*.id' => 'nullable|exists:coupon_tiers,id',
            'tiers.*.min_amount' => 'required_with:tiers|numeric|min:0',
            'tiers.*.max_amount' => 'nullable|numeric|gt:tiers.*.min_amount',
            'tiers.*.percentage' => 'required_with:tiers|numeric|min:0|max:100',
            'tiers.*.priority' => 'nullable|integer',
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
            'message' => $message,
            'errors' => $fieldErrors,
        ], 422));
    }
}
