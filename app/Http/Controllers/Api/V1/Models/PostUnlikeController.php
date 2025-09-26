<?php

namespace App\Http\Controllers\Api\V1\Models;

use App\Http\Controllers\Controller;
use App\Models\ModelRepository;
use App\Models\Like;
use Illuminate\Http\JsonResponse;
use Exception;

class PostUnlikeController extends Controller
{
    /**
     * Remove a like from a model repository.
     */
    public function __invoke(string $uuid): JsonResponse
    {
        $model = ModelRepository::where('uuid', $uuid)->with('repository')->firstOrFail();
        $repository = $model->repository;
        $userId = auth()->id();

        try {
            $existingLike = Like::where('user_id', $userId)
                               ->where('repository_id', $repository->id)
                               ->first();

            if (!$existingLike) {
                return response()->sendError(
                    'You have not liked this model',
                    400
                );
            }

            $existingLike->delete();

            return response()->sendResponse(
                null,
                null,
                'Model unliked successfully',
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
