<?php

namespace App\Actions\AgentRuns;

use App\Actions\TaskChecklistItems\CreateTaskChecklistItemAction;
use App\Actions\TaskChecklistItems\UpdateTaskChecklistItemAction;
use App\Actions\TaskComments\AddTaskCommentAction;
use App\Actions\Tasks\UpdateTaskAction;
use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Enums\AgentRunActionStatus;
use App\Enums\AgentRunActionType;
use App\Enums\TaskPriority;
use App\Models\AgentRunAction as AgentRunActionModel;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\User;
use InvalidArgumentException;
use Throwable;

class ApplyAgentRunAction
{
    public function __construct(
        private readonly AddTaskCommentAction $addComment,
        private readonly CreateTaskChecklistItemAction $createChecklistItem,
        private readonly UpdateTaskChecklistItemAction $updateChecklistItem,
        private readonly UpdateTaskStatusAction $updateTaskStatus,
        private readonly UpdateTaskAction $updateTask,
    ) {}

    public function execute(AgentRunActionModel $action, ?User $approver = null): AgentRunActionModel
    {
        $action->loadMissing('run.board', 'run.task', 'run.agent');

        if ($action->status === AgentRunActionStatus::Applied) {
            return $action;
        }

        if ($action->status !== AgentRunActionStatus::Proposed) {
            return $this->markFailed($action, 'Only proposed actions can be applied.');
        }

        try {
            $this->apply($action);

            $action->forceFill([
                'status' => AgentRunActionStatus::Applied,
                'approved_by' => $approver?->id ?? $action->approved_by,
                'approved_at' => $approver ? now() : $action->approved_at,
                'applied_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            $message = $exception instanceof InvalidArgumentException
                ? $exception->getMessage()
                : 'The action could not be applied.';

            $this->markFailed($action, $message);
        }

        return $action->fresh();
    }

    private function apply(AgentRunActionModel $action): void
    {
        $run = $action->run;
        $board = $run->board;
        $task = $run->task;
        $agent = $run->agent;

        if (! $board || ! $task || ! $agent) {
            throw new InvalidArgumentException('The action is missing its board, task, or agent.');
        }

        match ($action->type) {
            AgentRunActionType::AddComment => $this->applyAddComment($action, $task, $agent),
            AgentRunActionType::AddChecklistItem => $this->applyAddChecklistItem($action, $task, $agent),
            AgentRunActionType::ToggleChecklistItem => $this->applyToggleChecklistItem($action, $task, $agent),
            AgentRunActionType::UpdateProgress => $this->applyUpdateProgress($action, $board, $task, $agent),
            AgentRunActionType::ChangeStatus => $this->applyChangeStatus($action, $board, $task, $agent),
            AgentRunActionType::UpdateTaskFields => $this->applyUpdateTaskFields($action, $board, $task, $agent),
        };
    }

    private function applyAddComment(AgentRunActionModel $action, Task $task, User $agent): void
    {
        $comment = trim((string) ($action->payload['comment'] ?? ''));

        if ($comment === '') {
            throw new InvalidArgumentException('The comment cannot be empty.');
        }

        $this->addComment->execute($agent, $task, [
            'content' => $comment,
        ]);
    }

    private function applyAddChecklistItem(AgentRunActionModel $action, Task $task, User $agent): void
    {
        $title = trim((string) ($action->payload['title'] ?? ''));

        if ($title === '') {
            throw new InvalidArgumentException('The checklist item title cannot be empty.');
        }

        $this->createChecklistItem->execute($task, [
            'title' => $title,
        ], $agent);
    }

    private function applyToggleChecklistItem(AgentRunActionModel $action, Task $task, User $agent): void
    {
        $itemId = (int) ($action->payload['checklist_item_id'] ?? 0);

        if ($itemId < 1 || ! array_key_exists('completed', $action->payload ?? [])) {
            throw new InvalidArgumentException('Choose a checklist item and completed state.');
        }

        $item = TaskChecklistItem::query()
            ->where('task_id', $task->id)
            ->find($itemId);

        if (! $item) {
            throw new InvalidArgumentException('The checklist item does not belong to this task.');
        }

        $this->updateChecklistItem->execute($item, [
            'completed' => (bool) $action->payload['completed'],
        ], $agent);
    }

    private function applyUpdateProgress(
        AgentRunActionModel $action,
        Board $board,
        Task $task,
        User $agent,
    ): void {
        $progress = $action->payload['progress'] ?? null;

        if (! is_int($progress) || $progress < 0 || $progress > 100) {
            throw new InvalidArgumentException('Progress must be between 0 and 100.');
        }

        $this->updateTask->execute($board, $task, [
            'progress' => $progress,
        ], $agent);
    }

    private function applyChangeStatus(
        AgentRunActionModel $action,
        Board $board,
        Task $task,
        User $agent,
    ): void {
        $status = (string) ($action->payload['status'] ?? '');

        if (! in_array($status, BoardColumn::statusesForBoard($board), true)) {
            throw new InvalidArgumentException('Choose a status from this board.');
        }

        $this->updateTaskStatus->execute($board, $task, $status, $agent);
    }

    private function applyUpdateTaskFields(
        AgentRunActionModel $action,
        Board $board,
        Task $task,
        User $agent,
    ): void {
        $fields = $action->payload['fields'] ?? null;

        if (! is_array($fields)) {
            throw new InvalidArgumentException('No task fields were provided.');
        }

        $data = [];

        foreach (['title', 'description', 'deadline_at'] as $field) {
            if (array_key_exists($field, $fields) && $fields[$field] !== null) {
                $data[$field] = $fields[$field];
            }
        }

        if (array_key_exists('tags', $fields) && $fields['tags'] !== null) {
            $data['tags'] = Task::normalizeTags($fields['tags']);
        }

        if (array_key_exists('priority', $fields) && $fields['priority'] !== null) {
            $priority = TaskPriority::tryFrom((string) $fields['priority']);

            if ($priority === null) {
                throw new InvalidArgumentException('Choose a supported task priority.');
            }

            $data['priority'] = $priority;
        }

        if ($data === []) {
            throw new InvalidArgumentException('No supported task fields were provided.');
        }

        $this->updateTask->execute($board, $task, $data, $agent);
    }

    private function markFailed(AgentRunActionModel $action, string $message): AgentRunActionModel
    {
        $action->forceFill([
            'status' => AgentRunActionStatus::Failed,
            'error_message' => $message,
        ])->save();

        return $action->fresh();
    }
}
