<?php

namespace App\Http\Controllers\Api\V1\Stats;

use App\Http\Controllers\Controller;
use App\Services\Api\V1\StatsService;
use App\Transformers\StatsTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class GetStatsController extends Controller
{
    private StatsService $statsService;
    private StatsTransformer $statsTransformer;

    public function __construct(StatsService $statsService, StatsTransformer $statsTransformer)
    {
        $this->statsService = $statsService;
        $this->statsTransformer = $statsTransformer;
    }

    /**
     * Get general application statistics.
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $stats = $this->statsService->getGeneralStats();
            $transformedStats = $this->statsTransformer->transform($stats);

            return response()->sendResponse(
                $transformedStats,
                null,
                'Statistics retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
