<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TaskPriority;
use App\Http\Requests\Concerns\NormalizesTaskInput;
use App\Http\Requests\Concerns\RejectsArchivedAgentAssignees;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Support\BoardTaskAssignments;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTaskRequest extends FormRequest
{
    use NormalizesTaskInput, RejectsArchivedAgentAssignees;

    public function authorize(): bool
    {
        $board = $this->route('board');
        $task = $this->route('task');

        if (
            ! $this->user()
            || ! $board instanceof Board
            || ! $task instanceof Task
        ) {
            return false;
        }

        Gate::forUser($this->user())->authorize('update', $board);
        abort_unless(
            BoardTaskAssignments::taskExistsOnBoard($board->id, $task->id),
            404,
        );

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedTaskInput());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $board = $this->route('board');
            $task = $this->route('task');
            $allowedArchivedAgentIds = $board instanceof Board && $task instanceof Task
                ? BoardTaskAssignments::assigneeIdsForBoardTask($board->id, $task->id)
                : [];

            $this->rejectArchivedAgentAssignees($validator, $allowedArchivedAgentIds);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $board = $this->route('board');
        $memberIdsRule = $board instanceof Board
            ? Rule::exists('board_members', 'user_id')
                ->where('board_id', $board->id)
            : Rule::exists('users', 'id');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'required', 'string', Rule::in(
                $board instanceof Board
                    ? BoardColumn::statusesForBoard($board)
                    : []
            )],
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'deadline_at' => ['sometimes', 'nullable', 'date'],
            'priority' => ['sometimes', 'required', Rule::enum(TaskPriority::class)],
            'tags' => ['sometimes', 'nullable', 'array', 'max:'.Task::MAX_TAGS],
            'tags.*' => ['string', 'max:'.Task::MAX_TAG_LENGTH],
            'assignee_ids' => ['sometimes', 'array', 'min:1'],
            'assignee_ids.*' => ['integer', $memberIdsRule],
        ];
    }
}
