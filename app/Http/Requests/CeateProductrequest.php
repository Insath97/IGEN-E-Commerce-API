<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CeateProductrequest extends FormRequest
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
        return [
            // Product basic info
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100|unique:products,code',
            'slug' => 'nullable|string|max:255|unique:products,slug',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'required|exists:brands,id',
            'type' => 'required|in:physical,digital,service',
            'status' => 'nullable|in:draft,published,archived',

            // Pricing (base prices - variants will have their own)
            'price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',

            // Descriptions
            'short_description' => 'nullable|string|max:500',
            'full_description' => 'nullable|string',

            // Images
            'primary_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg,webp',

            // Status flags
            'is_trending' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',

            // Features & Specifications
            'feature_name' => 'required|string|max:255',

            'specifications' => 'nullable|array',
            'specifications.*.specification_name' => 'required_with:specifications|string|max:255',
            'specifications.*.specification_value' => 'required_with:specifications|string',

            // Tags
            'tags' => 'nullable|string|max:1000',

            // Variants
            'variants' => 'nullable|array',
            'variants.*.variant_name' => 'nullable|string|max:255',
            'variants.*.sku' => 'required_with:variants|string|max:100|unique:product_variants,sku',
            'variants.*.barcode' => 'nullable|string|max:100|unique:product_variants,barcode',
            'variants.*.storage_size' => 'required_with:variants|string|max:50',
            'variants.*.ram_size' => 'required_with:variants|string|max:50',
            'variants.*.color' => 'required_with:variants|string|max:50',
            'variants.*.price' => 'required_with:variants|numeric|min:0',
            'variants.*.sales_price' => 'nullable|numeric|min:0',
            'variants.*.stock_quantity' => 'required_with:variants|integer|min:0',
            'variants.*.low_stock_threshold' => 'nullable|integer|min:0',
            'variants.*.is_offer' => 'nullable|boolean',
            'variants.*.offer_price' => 'nullable|numeric|min:0',
            'variants.*.is_trending' => 'nullable|boolean',
            'variants.*.is_active' => 'nullable|boolean',
            'variants.*.is_featured' => 'nullable|boolean',
            'variants.*.condition' => 'nullable|in:new,used,refurbished',

            // Relationships
            'compatible_product_ids' => 'nullable|integer|exists:products,id',

            'bundled_product_ids' => 'nullable|integer|exists:products,id',
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
