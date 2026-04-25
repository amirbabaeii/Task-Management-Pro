<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Services\Interfaces\Auth\AuthServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthController extends ApiController
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
    ) {}

    /**
     * Handle user login
     */
    public function login(LoginRequest $request): JsonResource
    {
        $request->authenticate();

        $result = $this->authService->login([
            'email' => $request->email,
            'password' => $request->password,
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
