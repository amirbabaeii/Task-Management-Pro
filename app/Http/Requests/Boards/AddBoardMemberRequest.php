<?php

namespace App\Http\Requests\Boards;

use App\Models\Board;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AddBoardMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => trim((string) $this->input('email')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $email = (string) $this->input('email');

            if ($email === '') {
                return;
            }

            $invitee = User::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                ->first();

            if (! $invitee) {
                $validator->errors()->add(
                    'email',
                    'No account is registered with that email.',
                );

                return;
            }

            if ($invitee->is_agent && $invitee->agent_archived_at !== null) {
                $validator->errors()->add(
                    'email',
                    'Archived agents cannot be added to boards.',
                );

                return;
            }

            $board = $this->route('board');

            if ($board instanceof Board && $board->hasMember($invitee)) {
                $validator->errors()->add(
                    'email',
                    'That user is already on this board.',
                );

                return;
            }

            // Stash the resolved invitee so the controller doesn't query again.
            $this->merge(['_invitee' => $invitee]);
        });
    }

    public function invitee(): ?User
    {
        $invitee = $this->input('_invitee');

        return $invitee instanceof User ? $invitee : null;
    }
}
