<?php

namespace App\Http\Controllers\Api\V1\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Search\SearchRepositoriesRequest;
use App\Services\Api\V1\RepositoryService;
use App\Transformers\SearchRepositoryTransformer;
use Illuminate\Http\JsonResponse;
use Exception;

class SearchRepositoriesController extends Controller
{
    private SearchRepositoryTransformer $searchTransformer;
    private RepositoryService $repositoryService;

    public function __construct(SearchRepositoryTransformer $searchTransformer, RepositoryService $repositoryService)
    {
        $this->searchTransformer = $searchTransformer;
        $this->repositoryService = $repositoryService;
    }

    /**
     * Search repositories by name across models and datasets.
     */
    public function __invoke(SearchRepositoriesRequest $request): JsonResponse
    {
        try {
            $query = $request->validated()['query'];
            $repositories = $this->repositoryService->searchRepositories($query);

            return response()->sendResponse(
                $repositories,
                $this->searchTransformer,
                'Search results retrieved successfully',
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
