<?php

namespace App\Http\Controllers\Api\V1\Models;

use App\Http\Controllers\Controller;
use App\Models\ModelRepository;
use App\Transformers\ModelRepositoryTransformer;
use Illuminate\Http\JsonResponse;
use Exception;

class GetModelController extends Controller
{
    private ModelRepositoryTransformer $modelTransformer;

    public function __construct(ModelRepositoryTransformer $modelTransformer)
    {
        $this->modelTransformer = $modelTransformer;
    }

    /**
     * Get a specific model with all its information.
     */
    public function __invoke(string $uuid): JsonResponse
    {
        $model = ModelRepository::where('uuid', $uuid)->firstOrFail();

        try {
            return response()->sendResponse(
                $model,
                $this->modelTransformer,
                'Model retrieved successfully',
                ['repository', 'repository.user', 'repository.category', 'repository.religiousMovement', 'repository.tags', 'repository.approver'],
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                404
            );
        }
    }
}
