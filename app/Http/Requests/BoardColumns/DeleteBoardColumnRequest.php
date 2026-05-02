<?php

namespace App\Http\Requests\BoardColumns;

use App\Models\Board;
use App\Models\BoardColumn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DeleteBoardColumnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $board = $this->route('board');
        $status = (string) $this->route('status');

        $available = $board instanceof Board
            ? BoardColumn::statusesForBoard($board)
            : [];

        $destinationCandidates = array_values(array_filter(
            $available,
            fn (string $candidate): bool => $candidate !== $status,
        ));

        return [
            'move_tasks_to' => ['nullable', 'string', Rule::in($destinationCandidates)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $board = $this->route('board');
            $status = (string) $this->route('status');

            if (! $board instanceof Board) {
                return;
            }

            $available = BoardColumn::statusesForBoard($board);

            if (! in_array($status, $available, true)) {
                $validator->errors()->add(
                    'status',
                    'Column not found on this board.',
                );

                return;
            }

            if (count($available) <= 1) {
                $validator->errors()->add(
                    'status',
                    'A board must keep at least one column.',
                );
            }
        });
    }
}
