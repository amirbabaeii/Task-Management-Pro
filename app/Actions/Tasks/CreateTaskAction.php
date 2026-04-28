<?php

namespace App\Actions\Tasks;

use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use App\Support\BoardTaskAssignments;
use Illuminate\Support\Facades\DB;

class CreateTaskAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $assignee, Board $board, array $data): Task
    {
        return DB::transaction(function () use ($assignee, $board, $data): Task {
            $task = Task::create($data);

            $task->users()->attach($assignee->id, [
                'board_id' => $board->id,
                'role' => 'assignee',
                'sort_order' => BoardTaskAssignments::nextSortOrderForUserStatus(
                    $assignee->id,
                    $board->id,
                    $task->status,
                ),
            ]);

            return $task;
        });
    }
}
