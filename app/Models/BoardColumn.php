<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class BoardColumn extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'board_id',
        'status',
        'label',
        'position',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /**
     * Default board columns seeded onto every new board, derived from TaskStatus.
     *
     * @return list<array{status: string, label: string}>
     */
    public static function defaultColumns(): array
    {
        return array_map(
            fn (TaskStatus $status): array => [
                'status' => $status->value,
                'label' => $status->label(),
            ],
            TaskStatus::cases(),
        );
    }

    public static function orderedForBoard(Board $board): Collection
    {
        return $board->columns()
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public static function statusesForBoard(Board $board): array
    {
        return self::orderedForBoard($board)
            ->pluck('status')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function statusesForUser(User $user): array
    {
        $board = $user->boards()
            ->orderBy('position')
            ->orderBy('id')
            ->first();

        return $board ? self::statusesForBoard($board) : [];
    }

    /**
     * @return array<string, string>
     */
    public static function labelsForBoard(Board $board): array
    {
        return self::orderedForBoard($board)
            ->pluck('label', 'status')
            ->map(fn (string $label): string => trim($label))
            ->all();
    }

    public static function nextPositionForBoard(Board $board): int
    {
        return ((int) $board->columns()->max('position')) + 1;
    }
}
