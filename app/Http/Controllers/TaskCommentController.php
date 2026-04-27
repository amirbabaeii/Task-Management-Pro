<?php

namespace App\Http\Controllers;

use App\Actions\TaskComments\AddTaskCommentAction;
use App\Http\Requests\Tasks\StoreTaskCommentRequest;
use App\Models\Task;
use App\Support\Presenters\TaskCommentPresenter;
use Illuminate\Http\JsonResponse;

class TaskCommentController extends Controller
{
    public function __construct(
        private readonly AddTaskCommentAction $addComment,
    ) {}

    public function store(StoreTaskCommentRequest $request, Task $task): JsonResponse
    {
        $comment = $this->addComment->execute(
            $request->user(),
            $task,
            $request->validated(),
        );

        return response()->json([
            'comment' => TaskCommentPresenter::toArray($comment),
        ], 201);
    }
}
