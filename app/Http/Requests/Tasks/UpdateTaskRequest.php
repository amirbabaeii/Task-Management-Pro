<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskPriority;
use App\Http\Requests\Concerns\NormalizesTaskInput;
use App\Http\Requests\Concerns\RejectsArchivedAgentAssignees;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Support\BoardTaskAssignments;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTaskRequest extends FormRequest
{
    use NormalizesTaskInput, RejectsArchivedAgentAssignees;

    public function authorize(): bool
    {
        $board = $this->route('board');
        $task = $this->route('task');

        return $board instanceof Board
            && $task instanceof Task
            && $this->user()?->can('update', $board)
            && BoardTaskAssignments::taskExistsOnBoard($board->id, $task->id);
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

    public function rules(): array
    {
        $board = $this->route('board');
        $memberIdsRule = $board instanceof Board
            ? Rule::exists('board_members', 'user_id')
                ->where('board_id', $board->id)
            : Rule::exists('users', 'id');

        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', Rule::in(
                $board instanceof Board
                    ? BoardColumn::statusesForBoard($board)
                    : BoardColumn::statusesForUser($this->user())
            )],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'deadline_at' => ['nullable', 'date'],
            'tags' => ['nullable', 'array', 'max:'.Task::MAX_TAGS],
            'tags.*' => ['string', 'max:'.Task::MAX_TAG_LENGTH],
            'assignee_ids' => ['nullable', 'array', 'min:1'],
            'assignee_ids.*' => ['integer', $memberIdsRule],
        ];
    }
}
