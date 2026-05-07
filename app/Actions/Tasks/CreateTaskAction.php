<?php

namespace App\Actions\Tasks;

use App\Enums\TaskActivityKind;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use App\Support\BoardTaskAssignments;
use Illuminate\Support\Facades\DB;

class CreateTaskAction
{
    public function __construct(
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    /**
     * Create a task on the board. Excluding `assignee_ids`, $data becomes the
     * Task model's fillable payload. Assignees default to [creator] when not
     * supplied, which preserves the original behaviour.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(User $creator, Board $board, array $data): Task
    {
        $assigneeIds = $this->resolveAssigneeIds($creator, $data);
        unset($data['assignee_ids']);

        return DB::transaction(function () use ($creator, $board, $data, $assigneeIds): Task {
            $task = Task::create($data);

            foreach ($assigneeIds as $userId) {
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

            $this->recordActivity->execute(
                $task,
                TaskActivityKind::Created,
                ['title' => $task->title],
                $creator,
            );

            return $task;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function resolveAssigneeIds(User $creator, array $data): array
    {
        $raw = $data['assignee_ids'] ?? null;

        if (! is_array($raw) || $raw === []) {
            return [$creator->id];
        }

        return array_values(array_unique(array_map('intval', $raw)));
    }
}
