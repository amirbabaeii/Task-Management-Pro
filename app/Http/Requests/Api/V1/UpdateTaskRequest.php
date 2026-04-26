<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TaskPriority;
use App\Models\BoardColumn;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $task = Task::findOrFail($this->route('task')->id);

        return $this->user()->can('update', $task);
    }

    protected function prepareForValidation(): void
    {
        $description = $this->input('description');
        $payload = [];

        if ($this->has('title')) {
            $payload['title'] = trim((string) $this->input('title'));
        }

        if ($this->has('description')) {
            $payload['description'] = filled($description)
                ? trim((string) $description)
                : null;
        }

        if ($this->has('tags')) {
            $payload['tags'] = Task::normalizeTags($this->input('tags'));
        }

        $this->merge($payload);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
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
