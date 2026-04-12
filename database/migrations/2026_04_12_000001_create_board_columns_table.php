<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('board_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'in-progress', 'completed']);
            $table->string('label', 40);
            $table->timestamps();

            $table->unique(['user_id', 'status']);
        });

        if (!Schema::hasColumn('users', 'board_status_labels')) {
            return;
        }

        $timestamps = [
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('users')
            ->select(['id', 'board_status_labels'])
            ->whereNotNull('board_status_labels')
            ->orderBy('id')
            ->lazy()
            ->each(function (object $user) use ($timestamps): void {
                $labels = $this->decodeStatusLabels($user->board_status_labels);

                if ($labels->isEmpty()) {
                    return;
                }

                $rows = $labels->map(function (string $label, string $status) use ($user, $timestamps): array {
                    return [
                        'user_id' => $user->id,
                        'status' => $status,
                        'label' => $label,
                        ...$timestamps,
                    ];
                })->values()->all();

                DB::table('board_columns')->upsert(
                    $rows,
                    ['user_id', 'status'],
                    ['label', 'updated_at'],
                );
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('board_status_labels');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('users', 'board_status_labels')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('board_status_labels')->nullable()->after('password');
            });
        }

        DB::table('board_columns')
            ->select(['user_id', 'status', 'label'])
            ->orderBy('user_id')
            ->orderBy('status')
            ->get()
            ->groupBy('user_id')
            ->each(function (Collection $columns, int|string $userId): void {
                $labels = $columns
                    ->mapWithKeys(function (object $column): array {
                        return [$column->status => $column->label];
                    })
                    ->all();

                DB::table('users')
                    ->where('id', $userId)
                    ->update([
                        'board_status_labels' => json_encode($labels, JSON_THROW_ON_ERROR),
                    ]);
            });

        Schema::dropIfExists('board_columns');
    }

    private function decodeStatusLabels(mixed $value): Collection
    {
        $decoded = is_array($value)
            ? $value
            : json_decode((string) $value, true);

        if (!is_array($decoded)) {
            return collect();
        }

        return collect($decoded)
            ->filter(function (mixed $label, mixed $status): bool {
                return in_array($status, ['pending', 'in-progress', 'completed'], true)
                    && is_string($label)
                    && trim($label) !== '';
            })
            ->map(fn (string $label): string => trim($label));
    }
};
