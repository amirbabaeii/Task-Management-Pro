<?php

namespace App\Actions\Tasks;

use App\Enums\TaskActivityKind;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Support\BoardTaskAssignments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class UpdateTaskAssigneesAction
{
    public function __construct(
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    /**
     * Replace the task's assignees on this board with the given user ids.
     *
     * @param  list<int>  $userIds
     */
    public function execute(Board $board, Task $task, array $userIds): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        DB::transaction(function () use ($board, $task, $userIds): void {
            $current = DB::table('task_user')
                ->where('task_id', $task->id)
                ->where('board_id', $board->id)
                ->where('role', 'assignee')
                ->pluck('user_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $toRemove = array_values(array_diff($current, $userIds));
            $toAdd = array_values(array_diff($userIds, $current));

            if ($toRemove !== []) {
                DB::table('task_user')
                    ->where('task_id', $task->id)
                    ->where('board_id', $board->id)
                    ->where('role', 'assignee')
                    ->whereIn('user_id', $toRemove)
                    ->delete();
            }

            $sortOrder = BoardTaskAssignments::sortOrderForBoardTask($board->id, $task->id)
                ?: BoardTaskAssignments::nextSortOrderForBoardStatus($board->id, $task->status);

            foreach ($toAdd as $userId) {
                $task->users()->attach($userId, [
                    'board_id' => $board->id,
                    'role' => 'assignee',
                    'sort_order' => $sortOrder,
                ]);
            }

            if ($toAdd !== [] || $toRemove !== []) {
                $changedIds = array_merge($toAdd, $toRemove);
                $changedUsers = User::query()
                    ->whereIn('id', $changedIds)
                    ->get()
                    ->keyBy('id');

                $this->recordActivity->execute(
                    $task,
                    TaskActivityKind::AssigneesChanged,
                    [
                        'added' => array_map(
                            fn (int $id): array => [
                                'id' => $id,
                                'name' => $changedUsers->get($id)?->name ?? 'Unknown user',
                            ],
                            $toAdd,
                        ),
                        'removed' => array_map(
                            fn (int $id): array => [
                                'id' => $id,
                                'name' => $changedUsers->get($id)?->name ?? 'Unknown user',
                            ],
                            $toRemove,
                        ),
                    ],
                );

                $actor = auth()->user();
                if ($actor !== null && $toAdd !== []) {
                    $newAssignees = $changedUsers
                        ->only($toAdd)
                        ->reject(fn (User $user) => $user->id === $actor->id)
                        ->values();

                    if ($newAssignees->isNotEmpty()) {
                        Notification::send(
                            $newAssignees,
                            new TaskAssignedNotification($task, $board, $actor),
                        );
                    }
                }
            }
        });
    }
}
