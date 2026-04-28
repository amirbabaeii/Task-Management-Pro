<?php

namespace App\Http\Requests\BoardColumns;

use App\Models\Board;
use App\Models\BoardColumn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReorderBoardColumnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->route('status'),
        ]);
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
            'before_status' => ['nullable', 'string', Rule::in($available)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('status') === $this->input('before_status')) {
                $validator->errors()->add(
                    'before_status',
                    'A column cannot be reordered relative to itself.',
                );
            }
        });
    }
}
