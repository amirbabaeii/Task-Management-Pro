<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ApiResource;

class TaskResource extends ApiResource
{
    public function __construct($resource, $message, $statusCode)
    {
        parent::__construct( $resource, $message, $statusCode);
    }
    
    public function toArray($request)
    {
        return parent::toArray($request);
    }
}





