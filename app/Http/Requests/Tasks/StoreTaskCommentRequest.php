<?php

namespace App\Http\Requests\Tasks;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTaskCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'content' => trim((string) $this->input('content')),
            'parent_id' => $this->filled('parent_id')
                ? (int) $this->input('parent_id')
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:1000'],
            'parent_id' => ['nullable', 'integer', Rule::exists('task_comments', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $task = $this->route('task');

            if (!$task instanceof Task || !$this->filled('parent_id')) {
                return;
            }

            $parent = TaskComment::query()->find($this->integer('parent_id'));

            if (!$parent) {
                return;
            }

            if ($parent->task_id !== $task->id) {
                $validator->errors()->add(
                    'parent_id',
                    'Replies must belong to the same task.',
                );

                return;
            }

            if ($parent->parent_id !== null) {
                $validator->errors()->add(
                    'parent_id',
                    'Replies can only be added to top-level comments.',
                );
            }
        });
    }
}
