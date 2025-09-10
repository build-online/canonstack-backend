<?php

namespace App\Http\Controllers\Api\V1\Models;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Models\PatchModelRequest;
use App\Models\ModelRepository;
use App\Services\Api\V1\ModelsService;
use App\Transformers\ModelRepositoryTransformer;
use Illuminate\Http\JsonResponse;
use Exception;

class PatchModelController extends Controller
{
    private ModelsService $modelsService;
    private ModelRepositoryTransformer $modelRepositoryTransformer;

    public function __construct(ModelsService $modelsService, ModelRepositoryTransformer $modelRepositoryTransformer)
    {
        $this->modelsService = $modelsService;
        $this->modelRepositoryTransformer = $modelRepositoryTransformer;
    }

    /**
     * Update a model's information and optionally replace its ZIP file.
     */
    public function __invoke(PatchModelRequest $request, string $uuid): JsonResponse
    {
        $model = ModelRepository::where('uuid', $uuid)
            ->with('repository')
            ->firstOrFail();

        if ($model->repository->user_id !== auth()->id()) {
            return response()->sendError(
                'You are not authorized to update this model',
                403
            );
        }

        try {
            $updatedModel = $this->modelsService->updateModel(
                $model,
                $request->validated(),
                $request->file('zip_file')
            );

            return response()->sendResponse(
                $updatedModel,
                $this->modelRepositoryTransformer,
                'Model updated successfully'
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
