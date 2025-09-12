<?php

namespace App\Http\Requests\Api\V1\Featured;

use Illuminate\Foundation\Http\FormRequest;

class GetFeaturedRepositoriesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => 'sometimes|string|in:models,datasets',
            'limit' => 'sometimes|integer|min:1|max:50',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in' => 'The type field must be either "models" or "datasets".',
            'limit.integer' => 'The limit must be a valid integer.',
            'limit.min' => 'The limit must be at least 1.',
            'limit.max' => 'The limit cannot exceed 50.',
        ];
    }

    /**
     * Get the validated query parameters.
     *
     * @return array
     */
    public function getQueryParams(): array
    {
        $validated = $this->validated();
        
        return [
            'type' => $validated['type'] ?? null,
            'limit' => $validated['limit'] ?? 10, // Default limit of 10
        ];
    }
}
