<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\ApiResource;
use App\Http\Resources\UserResource;

class RegisterResource extends ApiResource
{
    private $token;

    public function __construct($resource, $token = null)
    {
        parent::__construct($resource, 'User successfully registered', 201);
        $this->token = $token;
    }

    public function toArray($request)
    {
        return array_merge(parent::toArray($request), [
            'data' => [
                'user' => new UserResource($this->resource),
                'token' => $this->token,
            ],
        ]);
    }
} 