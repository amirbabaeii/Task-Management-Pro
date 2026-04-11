<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskBoardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $tasks = Task::query()
            ->whereHas('assignees', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            })
            ->orderBy('updated_at', 'desc')
            ->get([
                'id',
                'title',
                'description',
                'status',
                'priority',
                'deadline_at',
                'progress',
                'created_at',
            ]);

        return Inertia::render('Tasks/Board', [
            'tasks' => $tasks,
            'statuses' => Task::STATUSES,
            'priorities' => Task::PRIORITIES,
        ]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated): void {
            $task = Task::create($validated);

            $task->users()->attach($user->id, [
                'role' => 'assignee',
            ]);
        });

        return redirect()->route('tasks.board');
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $task->fill($request->validated());
        $task->save();

        return redirect()->route('tasks.board');
    }

    public function updateStatus(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(Task::STATUSES)],
        ]);

        $task->status = $validated['status'];
        $task->save();

        return response()->json([
            'task' => $task->only(['id', 'status']),
        ]);
    }

    public function updateProgress(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $task->progress = $validated['progress'];
        $task->save();

        return response()->json([
            'task' => $task->only(['id', 'progress']),
        ]);
    }
}
