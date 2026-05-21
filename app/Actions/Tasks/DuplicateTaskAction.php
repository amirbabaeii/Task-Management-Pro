<?php

namespace App\Actions\Tasks;

use App\Models\Board;
use App\Models\Task;
use App\Models\User;

class DuplicateTaskAction
{
    public function __construct(
        private readonly CreateTaskAction $createTask,
    ) {}

    public function execute(User $actor, Board $board, Task $source): Task
    {
        $payload = [
            'title' => $source->title.' (copy)',
            'description' => $source->description,
            'status' => $source->status,
            'priority' => $source->priority?->value,
            'progress' => $source->progress,
            'deadline_at' => $source->deadline_at?->toDateTimeString(),
            'tags' => $source->tags ?? [],
        ];

        return $this->createTask->execute($actor, $board, $payload);
    }
}
