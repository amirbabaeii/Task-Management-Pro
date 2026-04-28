<?php

namespace App\Http\Requests\BoardColumns;

use App\Models\Board;
use App\Models\BoardColumn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBoardColumnLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->route('status'),
            'label' => trim((string) $this->input('label')),
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
            'label' => ['required', 'string', 'max:40'],
        ];
    }
}
