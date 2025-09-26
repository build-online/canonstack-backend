<?php

namespace App\Http\Controllers\Api\V1\Models;

use App\Http\Controllers\Controller;
use App\Models\ModelRepository;
use App\Services\Api\V1\ModelsService;
use Illuminate\Http\JsonResponse;
use Exception;

class DeleteModelController extends Controller
{
    private ModelsService $modelsService;

    public function __construct(ModelsService $modelsService)
    {
        $this->modelsService = $modelsService;
    }

    /**
     * Delete a model and all its associated files.
     */
    public function __invoke(string $uuid): JsonResponse
    {
        $model = ModelRepository::where('uuid', $uuid)
            ->with('repository')
            ->firstOrFail();

        if ($model->repository->user_id !== auth()->id()) {
            return response()->sendError(
                'You are not authorized to delete this model',
                403
            );
        }

        try {
            $this->modelsService->deleteModel($model);

            return response()->sendResponse(
                null,
                null,
                'Model deleted successfully',
                [],
                [],
                204
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                404
            );
        }
    }
}
