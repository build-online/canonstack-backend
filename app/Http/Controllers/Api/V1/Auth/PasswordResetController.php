<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    /**
     * Reset the user's password using the reset token
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $this->validateRequest($request);
        
        $status = Password::reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
                
                $user->tokens()->delete();
                
                event(new PasswordReset($user));
            }
        );
        
        if ($status === Password::PASSWORD_RESET) {
            return response()->sendResponse(
                null,
                null,
                'Password reset successfully'
            );
        }
        
        // Handle different error cases with specific messages
        $errorMessage = match ($status) {
            Password::INVALID_TOKEN => 'The password reset token is expired or invalid.',
            Password::INVALID_USER => 'The email address is not correct.',
            Password::RESET_THROTTLED => 'Please wait before retrying password reset.',
            default => trans($status)
        };

        $errors = [
            'token' => $status === Password::INVALID_TOKEN ? [$errorMessage] : [],
            'email' => $status !== Password::INVALID_TOKEN ? [$errorMessage] : [],
        ];

        throw ValidationException::withMessages($errors);
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
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);
    }
}
