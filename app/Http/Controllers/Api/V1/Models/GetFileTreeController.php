<?php

namespace App\Http\Controllers\Api\V1\Models;

use App\Http\Controllers\Controller;
use App\Models\ModelRepository;
use App\Services\Api\V1\RepositoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class GetFileTreeController extends Controller
{
    private RepositoryService $repositoryService;

    public function __construct(RepositoryService $repositoryService)
    {
        $this->repositoryService = $repositoryService;
    }

    /**
     * Get complete file tree structure for a model repository.
     * This returns a hierarchical tree useful for tree view components.
     */
    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        $model = ModelRepository::where('uuid', $uuid)->firstOrFail();
        $repository = $model->repository;
        $maxDepth = $request->query('max_depth', 5);

        try {
            $fileTreeData = $this->repositoryService->getFileTree($repository, $maxDepth);
            
            return response()->sendResponse(
                $fileTreeData,
                null,
                'File tree retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }

}
