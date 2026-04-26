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

    /**
     * Get the user who owns the board column settings.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the board that owns the column.
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /**
     * Ensure the board has the default board columns.
     */
    public static function ensureDefaultsForBoard(Board $board): void
    {
        $existingStatuses = $board->columns()
            ->pluck('status')
            ->all();

        $rows = [];
        $timestamp = now();

        foreach (self::defaultColumns() as $index => $column) {
            if (in_array($column['status'], $existingStatuses, true)) {
                continue;
            }

            $rows[] = [
                'user_id' => $board->user_id,
                'board_id' => $board->id,
                'status' => $column['status'],
                'label' => $column['label'],
                'position' => $index + 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if ($rows === []) {
            return;
        }

        self::query()->insert($rows);
    }

    /**
     * Ensure the user has a default board with columns.
     */
    public static function ensureDefaultsForUser(User $user): void
    {
        self::ensureDefaultsForBoard(Board::ensureDefaultForUser($user));
    }

    /**
     * Get the ordered board columns for the board.
     */
    public static function orderedForBoard(Board $board): Collection
    {
        self::ensureDefaultsForBoard($board);

        return $board->columns()
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * Get the ordered board columns for the user's primary board.
     */
    public static function orderedForUser(User $user): Collection
    {
        return self::orderedForBoard(Board::ensureDefaultForUser($user));
    }

    /**
     * Get the available statuses for the board.
     *
     * @return array<int, string>
     */
    public static function statusesForBoard(Board $board): array
    {
        return self::orderedForBoard($board)
            ->pluck('status')
            ->all();
    }

    /**
     * Get the available statuses for the user's primary board.
     *
     * @return array<int, string>
     */
    public static function statusesForUser(User $user): array
    {
        return self::statusesForBoard(Board::ensureDefaultForUser($user));
    }

    /**
     * Get the display labels for the board's columns.
     *
     * @return array<string, string>
     */
    public static function labelsForBoard(Board $board): array
    {
        return self::orderedForBoard($board)
            ->pluck('label', 'status')
            ->map(fn (string $label): string => trim($label))
            ->all();
    }

    /**
     * Get the display labels for the user's primary board columns.
     *
     * @return array<string, string>
     */
    public static function labelsForUser(User $user): array
    {
        return self::labelsForBoard(Board::ensureDefaultForUser($user));
    }

    /**
     * Determine the next column position for the board.
     */
    public static function nextPositionForBoard(Board $board): int
    {
        self::ensureDefaultsForBoard($board);

        return ((int) $board->columns()->max('position')) + 1;
    }

    /**
     * Determine the next column position for the user's primary board.
     */
    public static function nextPositionForUser(User $user): int
    {
        return self::nextPositionForBoard(Board::ensureDefaultForUser($user));
    }

    /**
     * Persist an ordered list of statuses for the board.
     *
     * @param  array<int, string>  $statuses
     */
    public static function syncOrderForBoard(Board $board, array $statuses): void
    {
        self::ensureDefaultsForBoard($board);

        $timestamp = now();

        foreach (array_values($statuses) as $index => $status) {
            self::query()
                ->where('board_id', $board->id)
                ->where('status', $status)
                ->update([
                    'position' => $index + 1,
                    'updated_at' => $timestamp,
                ]);
        }
    }

    /**
     * Persist an ordered list of statuses for the user's primary board.
     *
     * @param  array<int, string>  $statuses
     */
    public static function syncOrderForUser(User $user, array $statuses): void
    {
        self::syncOrderForBoard(Board::ensureDefaultForUser($user), $statuses);
    }
}
