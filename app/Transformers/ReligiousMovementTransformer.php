<?php

namespace App\Transformers;

use App\Models\ReligiousMovement;
use League\Fractal\TransformerAbstract;

class ReligiousMovementTransformer extends TransformerAbstract
{
    public function transform(ReligiousMovement $religiousMovement): array
    {
        return [
            'uuid' => $religiousMovement->uuid,
            'main_religion' => $religiousMovement->main_religion,
            'branch' => $religiousMovement->branch,
            'created_at' => $religiousMovement->created_at->toISOString(),
            'updated_at' => $religiousMovement->updated_at->toISOString(),
        ];
    }
}
