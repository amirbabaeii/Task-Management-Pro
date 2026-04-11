<?php

namespace App\Http\Requests\Tasks;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task && $this->user()?->can('update', $task);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(Task::STATUSES)],
            'before_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')],
        ];
    }
}
