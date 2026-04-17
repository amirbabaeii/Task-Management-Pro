<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;

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
        ]);
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:1000'],
        ];
    }
}
