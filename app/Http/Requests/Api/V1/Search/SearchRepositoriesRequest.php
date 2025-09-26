<?php

namespace App\Http\Requests\Api\V1\Search;

use Illuminate\Foundation\Http\FormRequest;

class SearchRepositoriesRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'query' => [
                'required',
                'string',
                'min:3',
                'max:255'
            ]
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'query.required' => 'Search query is required.',
            'query.min' => 'Search query must be at least 3 characters long.',
            'query.max' => 'Search query cannot exceed 255 characters.',
        ];
    }
}
