<?php

namespace App\Transformers;

use App\Models\Tag;
use League\Fractal\TransformerAbstract;

class TagTransformer extends TransformerAbstract
{
    public function transform(Tag $tag): array
    {
        return [
            'uuid' => $tag->uuid,
            'name' => $tag->name,
            'created_at' => $tag->created_at->toISOString(),
            'updated_at' => $tag->updated_at->toISOString(),
        ];
    }
}
