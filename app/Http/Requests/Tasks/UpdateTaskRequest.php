<?php

namespace App\Http\Requests\Tasks;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task && $this->user()?->can('update', $task);
    }

    protected function prepareForValidation(): void
    {
        $description = $this->input('description');

        $this->merge([
            'title' => trim((string) $this->input('title')),
            'description' => filled($description) ? trim((string) $description) : null,
            'deadline_at' => $this->input('deadline_at') ?: null,
            'tags' => Task::normalizeTags($this->input('tags')),
        ]);
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
            'priority' => ['required', 'string', Rule::in(Task::PRIORITIES)],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'deadline_at' => ['nullable', 'date'],
            'tags' => ['nullable', 'array', 'max:'.Task::MAX_TAGS],
            'tags.*' => ['string', 'max:'.Task::MAX_TAG_LENGTH],
        ];
    }
}
