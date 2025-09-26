<?php

namespace App\Http\Controllers\Api\V1\Models;

use App\Http\Controllers\Controller;
use App\Models\ModelRepository;
use App\Models\Like;
use Illuminate\Http\JsonResponse;
use Exception;

class PostLikeController extends Controller
{
    /**
     * Like a model repository.
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

            if ($existingLike) {
                return response()->sendError(
                    'You have already liked this model',
                    400
                );
            }

            Like::create([
                'user_id' => $userId,
                'repository_id' => $repository->id,
            ]);

            return response()->sendResponse(
                null,
                null,
                'Model liked successfully',
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
