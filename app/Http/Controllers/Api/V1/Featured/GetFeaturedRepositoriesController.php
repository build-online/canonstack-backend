<?php

namespace App\Http\Controllers\Api\V1\Featured;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Featured\GetFeaturedRepositoriesRequest;
use App\Services\Api\V1\RepositoryService;
use App\Transformers\SearchRepositoryTransformer;
use Illuminate\Http\JsonResponse;
use Exception;

class GetFeaturedRepositoriesController extends Controller
{
    private RepositoryService $repositoryService;
    private SearchRepositoryTransformer $searchTransformer;

    public function __construct(RepositoryService $repositoryService, SearchRepositoryTransformer $searchTransformer)
    {
        $this->repositoryService = $repositoryService;
        $this->searchTransformer = $searchTransformer;
    }

    /**
     * Get featured repositories with optional type filtering.
     */
    public function __invoke(GetFeaturedRepositoriesRequest $request): JsonResponse
    {
        try {
            $params = $request->getQueryParams();
            
            $repositories = $this->repositoryService->getFeaturedRepositories(
                $params['type'], 
                $params['limit']
            );

            return response()->sendResponse(
                $repositories,
                $this->searchTransformer,
                'Featured repositories retrieved successfully',
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
