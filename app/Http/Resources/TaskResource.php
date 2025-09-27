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
            $response['data'] = $this->transformPaginatedData($this->resource);
        }

        return $response;
    }

    private function transformPaginatedData(LengthAwarePaginator $paginator): array
    {
        $paginated = $paginator->toArray();

        $paginated['data'] = array_map(
            static fn (array $task) => $task + ['data' => $task],
            $paginated['data']
        );

        return $paginated;
    }
}





