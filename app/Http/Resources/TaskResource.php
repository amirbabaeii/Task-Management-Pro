<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TaskResource extends ApiResource
{
    public function __construct($resource, $message, $statusCode)
    {
        parent::__construct($resource, $message, $statusCode);
    }

    public function toArray($request)
    {
        $response = parent::toArray($request);

        if ($this->resource instanceof LengthAwarePaginator) {
            $response['data'] = [
                'data' => $this->resource->toArray(),
            ];
        }

        return $response;
    }
}





