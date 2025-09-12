<?php

namespace App\Http\Controllers\Api\V1\Trending;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Trending\GetTrendingRepositoriesRequest;
use App\Services\Api\V1\RepositoryService;
use App\Transformers\TrendingTransformer;
use Illuminate\Http\JsonResponse;
use Exception;

class GetTrendingRepositoriesController extends Controller
{
    private RepositoryService $repositoryService;
    private TrendingTransformer $trendingTransformer;

    public function __construct(RepositoryService $repositoryService, TrendingTransformer $trendingTransformer)
    {
        $this->repositoryService = $repositoryService;
        $this->trendingTransformer = $trendingTransformer;
    }

    /**
     * Get trending repositories for a specific period.
     */
    public function __invoke(GetTrendingRepositoriesRequest $request): JsonResponse
    {
        try {
            $params = $request->getQueryParams();
            
            $trending = $this->repositoryService->getTrending($params['period']);
            $transformedTrending = $this->trendingTransformer->transform($trending);

            return response()->sendResponse(
                $transformedTrending,
                null,
                'Trending repositories retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
