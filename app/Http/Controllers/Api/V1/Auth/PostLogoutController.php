<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostLogoutController extends Controller
{
    /**
     * Handle user logout
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->sendResponse(
            null,
            null,
            'Logged out successfully'
        );
    }
}
