<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Board extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'position',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function columns(): HasMany
    {
        return $this->hasMany(BoardColumn::class);
    }

    public static function ensureDefaultForUser(User $user): self
    {
        $board = $user->boards()
            ->orderBy('position')
            ->orderBy('id')
            ->first();

        if (!$board) {
            $board = $user->boards()->create([
                'name' => 'My Board',
                'position' => 1,
            ]);
        }

        BoardColumn::ensureDefaultsForBoard($board);

        return $board;
    }

    public static function orderedForUser(User $user): Collection
    {
        self::ensureDefaultForUser($user);

        return $user->boards()
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public static function nextPositionForUser(User $user): int
    {
        self::ensureDefaultForUser($user);

        return ((int) $user->boards()->max('position')) + 1;
    }
}
