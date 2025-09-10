<?php

namespace App\Http\Controllers\Api\V1\Models;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Models\GetModelsRequest;
use App\Services\Api\V1\ModelsService;
use App\Transformers\ModelRepositoryTransformer;
use Illuminate\Http\JsonResponse;
use Exception;

class GetModelsController extends Controller
{
    private ModelsService $modelsService;
    private ModelRepositoryTransformer $modelRepositoryTransformer;

    public function __construct(ModelsService $modelsService, ModelRepositoryTransformer $modelRepositoryTransformer)
    {
        $this->modelsService = $modelsService;
        $this->modelRepositoryTransformer = $modelRepositoryTransformer;
    }

    /**
     * Get paginated list of models with filtering options.
     */
    public function __invoke(GetModelsRequest $request): JsonResponse
    {
        try {
            $filters = $request->getQueryParams();
            $models = $this->modelsService->getModels($filters);

            return response()->sendResponse(
                $models,
                $this->modelRepositoryTransformer,
                'Models retrieved successfully',
                ['repository', 'repository.user', 'repository.category', 'repository.religiousMovement', 'repository.tags', 'repository.approver'],
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
