<?php

namespace App\Transformers;

use App\Models\Category;
use League\Fractal\TransformerAbstract;

class CategoryTransformer extends TransformerAbstract
{
    public function transform(Category $category): array
    {
        return [
            'uuid' => $category->uuid,
            'name' => $category->name,
            'models_count' => $category->models_count ?? 0,
            'datasets_count' => $category->datasets_count ?? 0,
            'total_count' => $category->total_count ?? 0,
        ];
    }
}
