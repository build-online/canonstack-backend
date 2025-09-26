<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Transformers\UserTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetMeController extends Controller
{
    /**
     *
     * @var UserTransformer
     */
    private $userTransformer;

    /**
     *
     * @param UserTransformer $userTransformer
     */
    public function __construct(UserTransformer $userTransformer)
    {
        $this->userTransformer = $userTransformer;
    }

    /**
     * Get the authenticated user's information
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->sendResponse(
            $request->user(),
            $this->userTransformer,
            'User retrieved successfully',
        );
    }
}