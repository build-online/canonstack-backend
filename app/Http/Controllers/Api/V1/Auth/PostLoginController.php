<?php

namespace App\Http\Controllers\Api\V1\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;

use App\Http\Controllers\Controller;
use App\Models\User;

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

        if (auth()->attempt($data)) {
            /** @var User $me */
            $me = auth()->user();

            $me->tokens()->delete();
            $token = $me->createToken('app')->plainTextToken;

            return response()->sendResponse(['token' => $token], null, 'Logged in successfully');
        }

        throw new AuthenticationException('Wrong credentials');
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