<?php

namespace App\Models;

use App\Enums\BoardRole;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Board extends Model
{
    use HasFactory;

    public const DEFAULT_DESCRIPTION = 'Drag tasks between columns and reorder them within each status.';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'position',
    ];

    /**
     * Every Board has exactly one owner membership row, created automatically.
     */
    protected static function booted(): void
    {
        static::created(function (Board $board): void {
            if (! $board->user_id) {
                return;
            }

            $board->members()->syncWithoutDetaching([
                $board->user_id => [
                    'role' => BoardRole::Owner->value,
                    'joined_at' => now(),
                ],
            ]);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function columns(): HasMany
    {
        return $this->hasMany(BoardColumn::class);
    }

    /**
     * Every user with access to this board (owner + collaborators).
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'board_members')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function hasMember(User $user): bool
    {
        return $this->members()
            ->where('users.id', $user->id)
            ->exists();
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id;
    }

    /**
     * Boards owned by the user, ordered by position.
     */
    public static function orderedForUser(User $user): Collection
    {
        return $user->boards()
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public static function nextPositionForUser(User $user): int
    {
        return ((int) $user->boards()->max('position')) + 1;
    }
}
