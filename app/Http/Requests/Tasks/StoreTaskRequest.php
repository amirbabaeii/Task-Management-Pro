<?php

namespace App\Http\Requests\Tasks;

use App\Models\BoardColumn;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $description = $this->input('description');

        $this->merge([
            'title' => trim((string) $this->input('title')),
            'description' => filled($description) ? trim((string) $description) : null,
            'deadline_at' => $this->input('deadline_at') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', Rule::in(BoardColumn::statusesForUser($this->user()))],
            'priority' => ['required', 'string', Rule::in(Task::PRIORITIES)],
            'deadline_at' => ['nullable', 'date'],
        ];
    }
}
