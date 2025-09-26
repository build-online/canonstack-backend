<?php

namespace App\Http\Requests\Api\V1\Review;

use Illuminate\Foundation\Http\FormRequest;

class ReviewRepositoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'APPROVER';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => 'required|string|in:APPROVE,REJECT',
            'comment' => 'sometimes|string|min:1|max:1000',
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
            'action.required' => 'The action field is required.',
            'action.in' => 'The action must be either "APPROVE" or "REJECT".',
            'comment.string' => 'The comment must be a valid string.',
            'comment.min' => 'The comment must be at least 1 character.',
            'comment.max' => 'The comment cannot exceed 1000 characters.',
        ];
    }

    /**
     * Get the validated data for the review.
     *
     * @return array
     */
    public function getReviewData(): array
    {
        $validated = $this->validated();
        
        return [
            'action' => $validated['action'],
            'comment' => $validated['comment'] ?? null,
        ];
    }
}
