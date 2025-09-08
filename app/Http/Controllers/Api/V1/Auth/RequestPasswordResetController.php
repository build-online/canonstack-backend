<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class RequestPasswordResetController extends Controller
{
    /**
     * Send a password reset link to the user's email
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $this->validateRequest($request);
        
        // Send password reset link via email
        $status = Password::sendResetLink($data);
        
        if ($status === Password::RESET_LINK_SENT) {
            return response()->sendResponse(
                null,
                null,
                'Password reset link sent to your email address'
            );
        }
        
        // If email not found, return validation error
        throw ValidationException::withMessages([
            'email' => [trans($status)]
        ]);
    }
    
    /**
     * Validate the password reset request
     *
     * @param Request $request
     * @return array
     */
    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);
    }
}
