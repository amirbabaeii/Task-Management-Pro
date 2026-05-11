<?php

namespace App\Http\Requests\Tasks;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Support\BoardTaskAssignments;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $board = $this->route('board');
        $task = $this->route('task');

        return $board instanceof Board
            && $task instanceof Task
            && $this->user()?->can('update', $board)
            && BoardTaskAssignments::taskExistsOnBoard($board->id, $task->id);
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
