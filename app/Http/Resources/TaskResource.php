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
            $items = $this->resource->getCollection()->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'created_at' => $task->created_at,
                    'updated_at' => $task->updated_at,
                ];
            })->toArray();

            $meta = [
                'current_page' => $this->resource->currentPage(),
                'total' => $this->resource->total(),
                'per_page' => $this->resource->perPage(),
            ];

            $response['data'] = array_merge($meta, [
                'data' => array_merge(['data' => $items], $meta),
            ]);
        }

        return $response;
    }
}





