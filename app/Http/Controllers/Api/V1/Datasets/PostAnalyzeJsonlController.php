<?php

namespace App\Http\Controllers\Api\V1\Datasets;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Services\Api\V1\JsonlAnalyzerService;
use Illuminate\Http\JsonResponse;
use Exception;

class PostAnalyzeJsonlController extends Controller
{
    private JsonlAnalyzerService $jsonlAnalyzerService;

    public function __construct(JsonlAnalyzerService $jsonlAnalyzerService)
    {
        $this->jsonlAnalyzerService = $jsonlAnalyzerService;
    }

    /**
     * Analyze JSONL files in a dataset and return field structure.
     */
    public function __invoke(string $datasetUuid): JsonResponse
    {
        // Increase execution time for JSONL analysis
        set_time_limit(30); // Should be fast with HTTP range requests (~2-3 seconds)
        
        try {
            $dataset = Dataset::where('uuid', $datasetUuid)
                ->with('repository')
                ->firstOrFail();

            // Check if the authenticated user is the creator of the dataset
            if ($dataset->repository->user_id !== auth()->id()) {
                return response()->sendError(
                    'Only the dataset author can analyze JSONL files.',
                    403
                );
            }

            // Analyze JSONL structure
            $analysis = $this->jsonlAnalyzerService->analyzeDatasetJsonl($dataset);

            if (!$analysis) {
                return response()->sendError(
                    'No JSONL files found in the dataset or files are already embedded.',
                    404
                );
            }

            return response()->sendResponse(
                $analysis,
                null,
                'JSONL structure analyzed successfully.'
            );

        } catch (Exception $e) {
            return response()->sendError(
                'Failed to analyze JSONL structure: ' . $e->getMessage(),
                500
            );
        }
    }
}

