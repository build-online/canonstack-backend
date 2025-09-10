<?php

namespace App\Http\Controllers\Api\V1\Categories;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Transformers\CategoryTransformer;
use Illuminate\Http\JsonResponse;

class GetCategoriesController extends Controller
{
    private CategoryTransformer $categoryTransformer;

    public function __construct(CategoryTransformer $categoryTransformer)
    {
        $this->categoryTransformer = $categoryTransformer;
    }

    /**
     * Get all categories ordered alphabetically.
     */
    public function __invoke(): JsonResponse
    {
        $categories = Category::orderBy('name', 'asc')->get();

        return response()->sendResponse(
            $categories,
            $this->categoryTransformer,
            'Categories retrieved successfully'
        );
    }
}
