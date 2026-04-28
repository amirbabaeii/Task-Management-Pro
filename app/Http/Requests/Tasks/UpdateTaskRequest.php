<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskPriority;
use App\Http\Requests\Concerns\NormalizesTaskInput;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    use NormalizesTaskInput;

    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task && $this->user()?->can('update', $task);
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedTaskInput());
    }

    public function rules(): array
    {
        $board = $this->route('board');

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
        ];
    }
}
