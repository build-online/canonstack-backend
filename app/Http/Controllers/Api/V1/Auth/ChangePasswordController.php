<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangePasswordController extends Controller
{
    /**
     * Change the authenticated user's password
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $this->validateRequest($request);
        
        $user = $request->user();
        
        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.']
            ]);
        }
        
        $user->update([
            'password' => Hash::make($data['new_password'])
        ]);
        
        $currentToken = $user->currentAccessToken();
        $user->tokens()->where('id', '!=', $currentToken->id)->delete();
        
        return response()->sendResponse(
            null,
            null,
            'Password changed successfully'
        );
    }
    
    /**
     * Validate the change password request
     *
     * @param Request $request
     * @return array
     */
    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);
    }
}
