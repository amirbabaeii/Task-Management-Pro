<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TaskPriority;
use App\Http\Requests\Concerns\NormalizesTaskInput;
use App\Http\Requests\Concerns\RejectsArchivedAgentAssignees;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTaskRequest extends FormRequest
{
    use NormalizesTaskInput, RejectsArchivedAgentAssignees;

    public function authorize(): bool
    {
        $board = $this->route('board');

        if (! $this->user() || ! $board instanceof Board) {
            return false;
        }

        Gate::forUser($this->user())->authorize('update', $board);

        return true;
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
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', Rule::in(
                $board instanceof Board
                    ? BoardColumn::statusesForBoard($board)
                    : []
            )],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'deadline_at' => ['nullable', 'date'],
            'tags' => ['nullable', 'array', 'max:'.Task::MAX_TAGS],
            'tags.*' => ['string', 'max:'.Task::MAX_TAG_LENGTH],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', $memberIdsRule],
        ];
    }
}
