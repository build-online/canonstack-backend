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
     * Get all categories ordered alphabetically with counts.
     */
    public function __invoke(): JsonResponse
    {
        $categories = Category::withCount([
            'repositories as total_count',
            'repositories as models_count' => function ($query) {
                $query->whereHas('model');
            },
            'repositories as datasets_count' => function ($query) {
                $query->whereHas('dataset');
            }
        ])->orderBy('name', 'asc')->get();

        return response()->sendResponse(
            $categories,
            $this->categoryTransformer,
            'Categories retrieved successfully'
        );
    }
}
