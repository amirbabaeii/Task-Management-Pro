<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Services\Interfaces\Auth\AuthServiceInterface;
use App\Http\Resources\Auth\AuthResource;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class AuthController extends ApiController
{
    private AuthServiceInterface $authService;

    public function __construct(AuthServiceInterface $authService)
    {
        $this->middleware('auth:sanctum')->except(['login']);
        $this->authService = $authService;
    }

    /**
     * Handle user login
     */
    public function login(LoginRequest $request): JsonResource
    {
        $request->authenticate();
        
        $result = $this->authService->login([
            'email' => $request->email,
            'password' => $request->password
        ]);
        
        return new AuthResource(
            $result,
            'Login successful',
            200
        );
    }

    public function logout(Request $request): JsonResource
    {
        $this->authService->logout($request->user());
        return new AuthResource(
            null,
            'Logged out successfully',
            200
        );
    }
}
