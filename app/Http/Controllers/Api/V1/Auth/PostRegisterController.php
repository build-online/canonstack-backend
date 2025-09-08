<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Transformers\UserTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class PostRegisterController extends Controller
{
    /**
     *
     * @var UserTransformer
     */
    private UserTransformer $userTranformer;

    /**
     *
     * @param UserTransformer $userTranformer
     */
    public function __construct(UserTransformer $userTranformer)
    {
        $this->userTranformer = $userTranformer;
    }

    /**
     * Handle user registration
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $this->validateRequest($request);
        
        $data['password'] = Hash::make($data['password']);
        
        if (!isset($data['role'])) {
            $data['role'] = 'REGULAR';
        }
        
        $user = User::create($data);
        
        return response()->sendResponse([
            'user' => $this->userTranformer->transform($user),
            'token' => $user->createToken('app')->plainTextToken
        ], null, 'User registered successfully');
    }
    
    /**
     * Validate the registration request
     *
     * @param Request $request
     * @return array
     */
    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['sometimes', 'string', Rule::in(['REGULAR', 'APPROVER'])],
        ]);
    }
}