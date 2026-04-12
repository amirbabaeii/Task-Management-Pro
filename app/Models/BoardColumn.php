<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class BoardColumn extends Model
{
    use HasFactory;

    /**
     * Default board columns available to every user.
     *
     * @var array<int, array{status: string, label: string}>
     */
    public const DEFAULT_COLUMNS = [
        ['status' => 'pending', 'label' => 'Pending'],
        ['status' => 'in-progress', 'label' => 'In Progress'],
        ['status' => 'completed', 'label' => 'Completed'],
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'status',
        'label',
        'position',
    ];

    /**
     * Get the user who owns the board column settings.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ensure the user has the default board columns.
     */
    public static function ensureDefaultsForUser(User $user): void
    {
        $existingStatuses = $user->boardColumns()
            ->pluck('status')
            ->all();

        $rows = [];
        $timestamp = now();

        foreach (self::DEFAULT_COLUMNS as $index => $column) {
            if (in_array($column['status'], $existingStatuses, true)) {
                continue;
            }

            $rows[] = [
                'user_id' => $user->id,
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
     * Get the ordered board columns for the user.
     */
    public static function orderedForUser(User $user): Collection
    {
        self::ensureDefaultsForUser($user);

        return $user->boardColumns()
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * Get the available statuses for the user.
     *
     * @return array<int, string>
     */
    public static function statusesForUser(User $user): array
    {
        return self::orderedForUser($user)
            ->pluck('status')
            ->all();
    }

    /**
     * Get the display labels for the user's board columns.
     *
     * @return array<string, string>
     */
    public static function labelsForUser(User $user): array
    {
        return self::orderedForUser($user)
            ->pluck('label', 'status')
            ->map(fn (string $label): string => trim($label))
            ->all();
    }

    /**
     * Determine the next column position for the user.
     */
    public static function nextPositionForUser(User $user): int
    {
        self::ensureDefaultsForUser($user);

        return ((int) $user->boardColumns()->max('position')) + 1;
    }
}
