<?php

namespace App\Http\Resources\Auth;

use App\Http\Resources\ApiResource;

class AuthResource extends ApiResource
{
    public function __construct($resource, $message, $statusCode = 200)
    {
        parent::__construct( $resource, $message, $statusCode);
    }

    public function toArray($request)
    {
        return parent::toArray($request);
    }
}
