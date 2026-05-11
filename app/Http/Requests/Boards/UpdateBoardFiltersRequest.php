<?php

namespace App\Http\Requests\Boards;

use App\Enums\TaskPriority;
use App\Models\Board;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBoardFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => trim((string) $this->input('search', '')),
            'priorities' => array_values(array_unique(array_map(
                fn (mixed $priority): string => (string) $priority,
                is_array($this->input('priorities')) ? $this->input('priorities') : [],
            ))),
            'assignee_id' => $this->filled('assignee_id')
                ? (int) $this->input('assignee_id')
                : null,
            'deadline' => (string) $this->input('deadline', 'all'),
            'view' => (string) $this->input('view', 'active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $board = $this->route('board');
        $memberRule = $board instanceof Board
            ? Rule::exists('board_members', 'user_id')->where('board_id', $board->id)
            : Rule::exists('users', 'id');

        return [
            'search' => ['nullable', 'string', 'max:150'],
            'priorities' => ['array'],
            'priorities.*' => ['string', 'distinct', Rule::in(TaskPriority::values())],
            'assignee_id' => ['nullable', 'integer', $memberRule],
            'deadline' => ['required', 'string', Rule::in(['all', 'overdue', 'today', 'upcoming', 'none'])],
            'view' => ['required', 'string', Rule::in(['active', 'archived'])],
        ];
    }
}
