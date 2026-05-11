<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class TaskPolicy
{
    use HandlesAuthorization;

    /**
     * Assigned users can update legacy/API tasks. For board-scoped tasks,
     * any member of the board that contains the task can update it.
     */
    public function update(User $user, Task $task): Response
    {
        $isAssignee = $task->users()
            ->where('users.id', $user->id)
            ->wherePivot('role', 'assignee')
            ->exists();

        $isBoardMember = $task->users()
            ->wherePivot('role', 'assignee')
            ->join('board_members', function ($join) use ($user): void {
                $join
                    ->on('board_members.board_id', '=', 'task_user.board_id')
                    ->where('board_members.user_id', $user->id);
            })
            ->exists();

        return $isAssignee || $isBoardMember
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
