<?php

namespace App\Http\Requests\Api\V1\Models;

use Illuminate\Foundation\Http\FormRequest;

class GetModelsRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'page' => [
                'sometimes',
                'integer',
                'min:1'
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100'
            ],
            'category_uuid' => [
                'sometimes',
                'string',
                'exists:categories,uuid'
            ],
            'tag_uuid' => [
                'sometimes',
                'string',
                'exists:tags,uuid'
            ],
            'tag_uuids' => [
                'sometimes',
                'array'
            ],
            'tag_uuids.*' => [
                'string',
                'exists:tags,uuid'
            ],
            'user_uuid' => [
                'sometimes',
                'string',
                'exists:users,uuid'
            ],
            'status' => [
                'sometimes',
                'string',
                'in:PENDING_REVIEW,APPROVED,REJECTED'
            ],
            'name' => [
                'sometimes',
                'string',
                'max:255'
            ],
            'sort_by' => [
                'sometimes',
                'string',
                'in:name,created_at,updated_at,status'
            ],
            'sort_direction' => [
                'sometimes',
                'string',
                'in:asc,desc'
            ]
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'per_page.max' => 'Maximum 100 items per page allowed.',
            'category_uuid.exists' => 'The selected category is invalid.',
            'tag_uuid.exists' => 'The selected tag is invalid.',
            'tag_uuids.*.exists' => 'One or more selected tags are invalid.',
            'user_uuid.exists' => 'The selected user is invalid.',
            'status.in' => 'Status must be one of: PENDING_REVIEW, APPROVED, REJECTED.',
            'sort_by.in' => 'Sort field must be one of: name, created_at, updated_at, status.',
            'sort_direction.in' => 'Sort direction must be either asc or desc.',
        ];
    }

    /**
     * Get validated query parameters with defaults.
     */
    public function getQueryParams(): array
    {
        $validated = $this->validated();
        
        return [
            'page' => $validated['page'] ?? 1,
            'per_page' => $validated['per_page'] ?? 10,
            'category_uuid' => $validated['category_uuid'] ?? null,
            'tag_uuid' => $validated['tag_uuid'] ?? null,
            'tag_uuids' => $validated['tag_uuids'] ?? null,
            'user_uuid' => $validated['user_uuid'] ?? null,
            'status' => $validated['status'] ?? null,
            'name' => $validated['name'] ?? null,
            'sort_by' => $validated['sort_by'] ?? 'created_at',
            'sort_direction' => $validated['sort_direction'] ?? 'desc',
        ];
    }
}
