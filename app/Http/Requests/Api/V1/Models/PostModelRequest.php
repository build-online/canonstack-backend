<?php

namespace App\Http\Requests\Api\V1\Models;

use Illuminate\Foundation\Http\FormRequest;

class PostModelRequest extends FormRequest
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
        // Get max ZIP size from config and convert to kilobytes for Laravel validation
        $maxZipSizeKB = config('filesystem_limits.models.max_zip_size', 5 * 1024 * 1024 * 1024) / 1024;
        
        return [
            'zip_file' => [
                'required',
                'file',
                'mimes:zip',
                'max:' . $maxZipSizeKB
            ],
            'name' => [
                'required',
                'string',
                'max:255'
            ],
            'description' => [
                'required',
                'string',
                'max:1000'
            ],
            'category_uuid' => [
                'required',
                'string',
                'exists:categories,uuid'
            ],
            'tag_uuids' => [
                'required',
                'array',
                'min:1'
            ],
            'tag_uuids.*' => [
                'string',
                'exists:tags,uuid'
            ],
            'religious_movement_uuid' => [
                'required',
                'string',
                'exists:religious_movements,uuid'
            ]
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        $maxZipSizeLabel = config('filesystem_limits.size_labels.models.max_zip_size', '5GB');
        
        return [
            'zip_file.required' => 'A ZIP file is required.',
            'zip_file.mimes' => 'The file must be a ZIP archive.',
            'zip_file.max' => "The ZIP file cannot be larger than {$maxZipSizeLabel}.",
            'tag_uuids.required' => 'At least one tag must be selected.',
            'tag_uuids.*.exists' => 'One or more selected tags are invalid.',
            'category_uuid.exists' => 'The selected category is invalid.',
            'religious_movement_uuid.exists' => 'The selected religious movement is invalid.',
        ];
    }
}
