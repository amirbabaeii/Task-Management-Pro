<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskPriority;
use App\Http\ApiResponse;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\StoreTaskRequest;
use App\Http\Requests\Api\V1\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $priority = $request->input('priority');

        $tasks = Task::query()
            ->when(
                in_array($priority, TaskPriority::values(), true),
                fn ($query) => $query->where('priority', $priority),
            )
            ->paginate(10)
            ->through(fn (Task $task) => (new TaskResource($task))->resolve());

        return ApiResponse::success($tasks, 'List of tasks successfully received');
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = Task::create($request->validated());

        return ApiResponse::success(
            new TaskResource($task),
            'Task successfully created',
            201,
        );
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $task->fill($request->validated());
        $task->save();

        return ApiResponse::success(
            new TaskResource($task),
            'Task successfully updated',
        );
    }
}
