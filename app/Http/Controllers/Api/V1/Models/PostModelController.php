<?php

namespace App\Http\Controllers\Api\V1\Models;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Models\PostModelRequest;
use App\Services\Api\V1\ModelsService;
use App\Transformers\ModelRepositoryTransformer;
use Illuminate\Http\JsonResponse;
use Exception;

class PostModelController extends Controller
{
    private ModelsService $modelsService;
    private ModelRepositoryTransformer $modelRepositoryTransformer;

    public function __construct(ModelsService $modelsService, ModelRepositoryTransformer $modelRepositoryTransformer)
    {
        $this->modelsService = $modelsService;
        $this->modelRepositoryTransformer = $modelRepositoryTransformer;
    }

    /**
     * Upload a new model.
     */
    public function __invoke(PostModelRequest $request): JsonResponse
    {
        // Increase execution time limit for model upload and processing
        set_time_limit(120);
        
        try {
            $model = $this->modelsService->uploadModel(
                $request->validated(),
                $request->file('zip_file')
            );

            return response()->sendResponse(
                $model,
                $this->modelRepositoryTransformer,
                'Model uploaded successfully and is pending review.'
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
