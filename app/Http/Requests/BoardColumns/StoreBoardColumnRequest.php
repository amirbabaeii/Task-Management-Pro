<?php

namespace App\Http\Requests\BoardColumns;

use Illuminate\Foundation\Http\FormRequest;

class StoreBoardColumnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'label' => trim((string) $this->input('label')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:40'],
        ];
    }
}
