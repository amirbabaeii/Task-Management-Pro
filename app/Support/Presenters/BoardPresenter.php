<?php

namespace App\Support\Presenters;

use App\Enums\BoardRole;
use App\Models\Board;
use App\Models\User;
use Illuminate\Support\Collection;

class BoardPresenter
{
    /**
     * @return array{id: int, name: string, description: string|null, role: string, is_owner: bool}
     */
    public static function navigation(Board $board, User $user): array
    {
        $isOwner = $board->isOwnedBy($user);

        return [
            'id' => $board->id,
            'name' => $board->name,
            'description' => $board->description,
            'role' => $isOwner
                ? BoardRole::Owner->value
                : (string) ($board->pivot?->role ?? BoardRole::Collaborator->value),
            'is_owner' => $isOwner,
        ];
    }

    /**
     * @param  Collection<int, Board>  $boards
     * @return list<array{id: int, name: string, description: string|null, role: string, is_owner: bool}>
     */
    public static function collection(Collection $boards, User $user): array
    {
        return $boards
            ->map(fn (Board $board): array => self::navigation($board, $user))
            ->values()
            ->all();
    }
}
