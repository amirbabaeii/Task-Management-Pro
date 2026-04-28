<?php

namespace App\Http\Requests\Tasks;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task && $this->user()?->can('update', $task);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $board = $this->route('board');
        $available = $board instanceof Board
            ? BoardColumn::statusesForBoard($board)
            : [];

        return [
            'status' => ['required', 'string', Rule::in($available)],
        ];
    }
}
