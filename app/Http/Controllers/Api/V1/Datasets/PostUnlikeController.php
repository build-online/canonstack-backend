<?php

namespace App\Http\Controllers\Api\V1\Datasets;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Models\Like;
use Illuminate\Http\JsonResponse;
use Exception;

class PostUnlikeController extends Controller
{
    /**
     * Remove a like from a dataset repository.
     */
    public function __invoke(string $uuid): JsonResponse
    {
        $dataset = Dataset::where('uuid', $uuid)->with('repository')->firstOrFail();
        $repository = $dataset->repository;
        $userId = auth()->id();

        try {
            $existingLike = Like::where('user_id', $userId)
                               ->where('repository_id', $repository->id)
                               ->first();

            if (!$existingLike) {
                return response()->sendError(
                    'You have not liked this dataset',
                    400
                );
            }

            $existingLike->delete();

            return response()->sendResponse(
                null,
                null,
                'Dataset unliked successfully',
                [],
                [],
                201
            );
        } catch (Exception $e) {
            return response()->sendError(
                $e->getMessage(),
                400
            );
        }
    }
}
