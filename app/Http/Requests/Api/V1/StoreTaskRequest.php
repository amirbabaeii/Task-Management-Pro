<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TaskPriority;
use App\Http\Requests\Concerns\NormalizesTaskInput;
use App\Models\BoardColumn;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    use NormalizesTaskInput;

    public function authorize(): bool
    {
        return true;
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', Rule::in(BoardColumn::statusesForUser($this->user()))],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'tags' => ['nullable', 'array', 'max:'.Task::MAX_TAGS],
            'tags.*' => ['string', 'max:'.Task::MAX_TAG_LENGTH],
        ];
    }
}
