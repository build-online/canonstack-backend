<?php

namespace App\Http\Controllers\Api\V1\Tags;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Transformers\TagTransformer;
use Illuminate\Http\JsonResponse;

class GetTagsController extends Controller
{
    private TagTransformer $tagTransformer;

    public function __construct(TagTransformer $tagTransformer)
    {
        $this->tagTransformer = $tagTransformer;
    }

    /**
     * Get all tags ordered alphabetically.
     */
    public function __invoke(): JsonResponse
    {
        $tags = Tag::orderBy('name', 'asc')->get();

        return response()->sendResponse(
            $tags,
            $this->tagTransformer,
            'Tags retrieved successfully'
        );
    }
}
