<?php

namespace App\Actions\Tasks;

use App\Enums\TaskActivityKind;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use App\Support\BoardTaskAssignments;
use Illuminate\Support\Facades\DB;

class UpdateTaskAssigneesAction
{
    public function __construct(
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    /**
     * Replace the task's assignees on this board with the given user ids.
     *
     * - Removes pivot rows for users no longer in the list (those users lose
     *   visibility of the task on this board).
     * - Adds pivot rows for new users at the end of their column for the
     *   task's current status.
     * - Existing assignees keep their sort_order.
     *
     * Caller is responsible for validating that every userId is a board
     * member. The action does not re-validate.
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

            foreach ($toAdd as $userId) {
                $task->users()->attach($userId, [
                    'board_id' => $board->id,
                    'role' => 'assignee',
                    'sort_order' => BoardTaskAssignments::nextSortOrderForUserStatus(
                        $userId,
                        $board->id,
                        $task->status,
                    ),
                ]);
            }

            if ($toAdd !== [] || $toRemove !== []) {
                $changedIds = array_merge($toAdd, $toRemove);
                $names = User::query()
                    ->whereIn('id', $changedIds)
                    ->pluck('name', 'id');

                $this->recordActivity->execute(
                    $task,
                    TaskActivityKind::AssigneesChanged,
                    [
                        'added' => array_map(
                            fn (int $id): array => [
                                'id' => $id,
                                'name' => $names->get($id, 'Unknown user'),
                            ],
                            $toAdd,
                        ),
                        'removed' => array_map(
                            fn (int $id): array => [
                                'id' => $id,
                                'name' => $names->get($id, 'Unknown user'),
                            ],
                            $toRemove,
                        ),
                    ],
                );
            }
        });
    }
}
