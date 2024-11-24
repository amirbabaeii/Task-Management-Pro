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
     * Determine if the user can update the task.
     */
    public function update(User $user, Task $task): Response
    {
        return $task->users()
            ->where('user_id', $user->id)
            ->where('role', 'assignee')
            ->exists() 
            ? Response::allow()
            : Response::deny('This task is not assigned to you.');
    }
} 