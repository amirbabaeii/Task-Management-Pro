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

    public const DEFAULT_DESCRIPTION = 'Drag tasks between columns and reorder them within each status.';

    protected $fillable = [
        'user_id',
        'name',
        'description',
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
