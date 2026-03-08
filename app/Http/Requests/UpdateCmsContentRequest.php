<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCmsContentRequest extends FormRequest
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
            'contents' => 'required|array',
            'contents.*.page' => 'required|string',
            'contents.*.section' => 'required|string',
            'contents.*.key' => 'required|string',
            'contents.*.value' => 'nullable', // Can be file or string
            'contents.*.type' => 'required|string|in:text,textarea,image,link',
            'contents.*.label' => 'nullable|string',
        ];
    }
}
