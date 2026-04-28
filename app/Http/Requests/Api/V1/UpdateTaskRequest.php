<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TaskPriority;
use App\Http\Requests\Concerns\NormalizesTaskInput;
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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'required', 'string', Rule::in(BoardColumn::statusesForUser($this->user()))],
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'deadline_at' => ['sometimes', 'nullable', 'date'],
            'priority' => ['sometimes', 'required', Rule::enum(TaskPriority::class)],
            'tags' => ['sometimes', 'nullable', 'array', 'max:'.Task::MAX_TAGS],
            'tags.*' => ['string', 'max:'.Task::MAX_TAG_LENGTH],
        ];
    }
}
