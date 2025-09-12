<?php

namespace App\Http\Requests\Api\V1\Trending;

use Illuminate\Foundation\Http\FormRequest;

class GetTrendingRepositoriesRequest extends FormRequest
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
            'period' => 'sometimes|string|in:week,month,year,all_time',
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
            'period.in' => 'The period must be one of: week, month, year, all_time.',
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
            'period' => $validated['period'] ?? 'all_time',
        ];
    }
}