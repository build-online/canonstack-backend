<?php

namespace App\Http\Requests\Api\V1\Comments;

use Illuminate\Foundation\Http\FormRequest;

class PatchCommentRequest extends FormRequest
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
            'text' => [
                'required',
                'string',
                'max:1000',
                'min:1'
            ]
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'text.required' => 'Comment text is required.',
            'text.max' => 'Comment cannot be longer than 1000 characters.',
            'text.min' => 'Comment cannot be empty.',
        ];
    }
}
