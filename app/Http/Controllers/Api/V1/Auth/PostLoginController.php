<?php

namespace App\Http\Controllers\Api\V1\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Events\UserLoggedIn;

class PostLoginController extends Controller
{
    /**
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $this->validateRequest($request);
        
        $email = $data['email'];
        $password = $data['password'];

        if (auth()->attempt(['email' => $email, 'password' => $password])) {
            $user = User::findOrFail(auth()->id());
            $user->tokens()->delete();
            
            $token = $user->createToken($user->uuid)->plainTextToken;

            // Dispatch user logged in event
            event(new UserLoggedIn($user));

            return response()->sendResponse(['token' => $token], null, 'Logged in successfully');
        }

        throw ValidationException::withMessages(['The provided credentials are incorrect.']);
    }

    /**
     * Validate the login request.
     *
     * @param Request $request
     * @return array
     */
    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
    }
}