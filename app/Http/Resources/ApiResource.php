<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ApiResource extends JsonResource
{
    protected $message;
    protected $statusCode;

    public function __construct($resource, $message = null, $statusCode = 200)
    {
        parent::__construct($resource);
        $this->message = $message;
        $this->statusCode = $statusCode;
    }

    public function toArray($request)
    {
        $output =[
            'type' => ($this->statusCode >= 200 && $this->statusCode < 300) ? "success" : "error",
            'message' => $this->message,
        ];
        if(isset($this->resource)){
            $output['data'] = $this->resource;
        }
        return $output;
    }

    public function withResponse($request, $response)
    {
        $response->setStatusCode($this->statusCode);
    }
} 