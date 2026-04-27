<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskPriority;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\StoreTaskRequest;
use App\Http\Requests\Api\V1\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends ApiController
{
    public function index(Request $request): TaskResource
    {
        $priority = $request->input('priority');

        $tasks = Task::query()
            ->when(
                in_array($priority, TaskPriority::values(), true),
                fn ($query) => $query->where('priority', $priority),
            )
            ->paginate(10);

        return new TaskResource($tasks, 'List of tasks successfully received', 200);
    }

    public function create()
    {
        //
    }

    public function store(StoreTaskRequest $request): TaskResource
    {
        $task = Task::create($request->validated());

        return new TaskResource($task, 'Task successfully created', 201);
    }

    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        //
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $task->fill($request->validated());
        $task->save();

        return new TaskResource($task, 'Task successfully updated', 200);
    }

    public function destroy(string $id)
    {
        //
    }
}
