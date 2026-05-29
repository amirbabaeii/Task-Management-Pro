<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskPriority;
use App\Http\Requests\Concerns\NormalizesTaskInput;
use App\Http\Requests\Concerns\RejectsArchivedAgentAssignees;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTaskRequest extends FormRequest
{
    use NormalizesTaskInput, RejectsArchivedAgentAssignees;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedTaskInput());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectArchivedAgentAssignees($validator);
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
            'deadline_at' => ['nullable', 'date'],
            'tags' => ['nullable', 'array', 'max:'.Task::MAX_TAGS],
            'tags.*' => ['string', 'max:'.Task::MAX_TAG_LENGTH],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', $memberIdsRule],
        ];
    }
}
