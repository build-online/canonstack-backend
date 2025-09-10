<?php

namespace App\Http\Controllers\Api\V1\ReligiousMovements;

use App\Http\Controllers\Controller;
use App\Models\ReligiousMovement;
use App\Transformers\ReligiousMovementTransformer;
use Illuminate\Http\JsonResponse;

class GetReligiousMovementsController extends Controller
{
    private ReligiousMovementTransformer $religiousMovementTransformer;

    public function __construct(ReligiousMovementTransformer $religiousMovementTransformer)
    {
        $this->religiousMovementTransformer = $religiousMovementTransformer;
    }

    /**
     * Get all religious movements grouped by main religion.
     */
    public function __invoke(): JsonResponse
    {
        $religiousMovements = ReligiousMovement::orderBy('main_religion', 'asc')
            ->orderBy('branch', 'asc')
            ->get();

        $grouped = $religiousMovements->groupBy('main_religion')->map(function ($movements, $religion) {
            return [
                'main_religion' => $religion,
                'movements' => $movements->map(function ($movement) {
                    return $this->religiousMovementTransformer->transform($movement);
                })->values()
            ];
        })->values();

        return response()->sendResponse(
            $grouped,
            null,
            'Religious movements retrieved successfully'
        );
    }
}
