<?php

namespace App\Http\Requests;

use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateProductrequest extends FormRequest
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
        $productId = $this->route('product') ?? $this->route('id');

        return [
            // Product basic info
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:100|unique:products,code,' . $productId,
            'slug' => 'sometimes|string|max:255|unique:products,slug,' . $productId,
            'category_id' => 'sometimes|exists:categories,id',
            'brand_id' => 'sometimes|exists:brands,id',
            'type' => 'sometimes|in:physical,digital,service',
            'status' => 'nullable|in:draft,published,archived',

            // Descriptions
            'short_description' => 'nullable|string|max:500',
            'full_description' => 'nullable|string',

            // Images
            'primary_image_path' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',

            // Status flags
            'is_trending' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',

            // Features (comma-separated string)
            'feature_name' => 'nullable|string|max:1000',

            // Specifications (array format)
            'specifications' => 'nullable|array',
            'specifications.*.specification_name' => 'required_with:specifications|string|max:255',
            'specifications.*.specification_value' => 'required_with:specifications|string',

            // Tags (comma-separated string)
            'tags' => 'nullable|string|max:1000',

            // Variants - FIXED with cleaned values
            'variants' => 'nullable|array',
            'variants.*.id' => 'nullable|exists:product_variants,id',
            'variants.*.variant_name' => 'nullable|string|max:255',
            'variants.*.sku' => [
                'sometimes',
                'string',
                'max:100',
                function ($attribute, $value, $fail) use ($productId) {
                    // Clean the value first
                    $value = $this->cleanStringValue($value);

                    $index = $this->extractArrayIndex($attribute);
                    $variantId = $this->input("variants.{$index}.id") ?? null;

                    // Query to check SKU uniqueness
                    $query = ProductVariant::where('sku', $value);

                    // Exclude current variant if it exists (for update)
                    if ($variantId) {
                        $query->where('id', '!=', $variantId);
                    }

                    $query->whereHas('product', function ($q) use ($productId) {
                        $q->where('id', '!=', $productId);
                    });

                    if ($query->exists()) {
                        $fail("The SKU '{$value}' has already been taken in another product.");
                    }
                }
            ],
            'variants.*.barcode' => [
                'nullable',
                'string',
                'max:100',
                function ($attribute, $value, $fail) use ($productId) {
                    // Clean the value first
                    $value = $this->cleanStringValue($value);

                    if (empty($value)) return;

                    $index = $this->extractArrayIndex($attribute);
                    $variantId = $this->input("variants.{$index}.id") ?? null;

                    $query = ProductVariant::where('barcode', $value);

                    if ($variantId) {
                        $query->where('id', '!=', $variantId);
                    }

                    // Also exclude variants from the same product
                    $query->whereHas('product', function ($q) use ($productId) {
                        $q->where('id', '!=', $productId);
                    });

                    if ($query->exists()) {
                        $fail("The barcode '{$value}' has already been taken in another product.");
                    }
                }
            ],
            'variants.*.storage_size' => 'sometimes|string|max:50',
            'variants.*.ram_size' => 'sometimes|string|max:50',
            'variants.*.color' => 'sometimes|string|max:50',
            'variants.*.price' => 'sometimes|numeric|min:0',
            'variants.*.sales_price' => 'nullable|numeric|min:0',
            'variants.*.stock_quantity' => 'sometimes|integer|min:0',
            'variants.*.low_stock_threshold' => 'nullable|integer|min:0',
            'variants.*.is_offer' => 'nullable|boolean',
            'variants.*.offer_price' => 'nullable|numeric|min:0',
            'variants.*.is_trending' => 'nullable|boolean',
            'variants.*.is_active' => 'nullable|boolean',
            'variants.*.is_featured' => 'nullable|boolean',
            'variants.*.condition' => 'sometimes|nullable|in:new,used,refurbished',

            // Relationships (comma-separated strings)
            'compatible_product_ids' => 'nullable|string|max:1000',
            'bundled_product_ids' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Extract array index from attribute name
     * Example: "variants.0.sku" returns 0
     */
    private function extractArrayIndex(string $attribute): ?int
    {
        if (preg_match('/variants\.(\d+)\./', $attribute, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    /**
     * Clean string value by removing extra quotes
     */
    private function cleanStringValue($value): string
    {
        if (!is_string($value)) {
            return $value;
        }

        // Remove leading/trailing quotes
        $value = trim($value);

        // Remove escaped quotes
        $value = str_replace('\"', '', $value);
        $value = str_replace('"', '', $value);

        // Remove single quotes if they wrap the entire string
        if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
            $value = substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * Clean variant data by removing extra quotes from all fields
     */
    private function cleanVariantData(array $variant): array
    {
        $cleaned = [];
        foreach ($variant as $key => $value) {
            if (is_string($value)) {
                $cleaned[$key] = $this->cleanStringValue($value);
            } elseif (is_array($value)) {
                $cleaned[$key] = $this->cleanVariantData($value);
            } else {
                $cleaned[$key] = $value;
            }
        }
        return $cleaned;
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation()
    {
        // Convert JSON strings to arrays if needed
        $this->convertJsonToArray('specifications');
        $this->convertJsonToArray('variants');

        // Clean variant values if variants is an array
        if ($this->has('variants') && is_array($this->input('variants'))) {
            $cleanedVariants = [];
            foreach ($this->input('variants') as $index => $variant) {
                $cleanedVariants[$index] = $this->cleanVariantData($variant);
            }
            $this->merge(['variants' => $cleanedVariants]);
        }
    }

    /**
     * Convert JSON string to array if needed
     */
    private function convertJsonToArray(string $field): void
    {
        if ($this->has($field) && is_string($this->input($field))) {
            try {
                $decoded = json_decode($this->input($field), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $this->merge([$field => $decoded]);
                }
            } catch (\Exception $e) {
                // Keep original value
            }
        }
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
