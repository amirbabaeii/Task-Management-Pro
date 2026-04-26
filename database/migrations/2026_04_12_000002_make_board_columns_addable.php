<?php

use App\Models\BoardColumn;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('board_columns', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('label');
        });

        $this->makeStatusColumnsFlexible();
        $this->seedDefaultBoardColumns();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('tasks')
            ->whereNotIn('status', collect(BoardColumn::defaultColumns())->pluck('status')->all())
            ->update([
                'status' => 'pending',
            ]);

        DB::table('board_columns')
            ->whereNotIn('status', collect(BoardColumn::defaultColumns())->pluck('status')->all())
            ->delete();

        $this->restoreDefaultStatusColumns();

        Schema::table('board_columns', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }

    private function makeStatusColumnsFlexible(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE tasks MODIFY status VARCHAR(100) NOT NULL DEFAULT 'pending'");
            DB::statement("ALTER TABLE board_columns MODIFY status VARCHAR(100) NOT NULL");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE tasks ALTER COLUMN status TYPE VARCHAR(100) USING status::text");
            DB::statement("ALTER TABLE tasks ALTER COLUMN status SET DEFAULT 'pending'");
            DB::statement("ALTER TABLE board_columns ALTER COLUMN status TYPE VARCHAR(100) USING status::text");

            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('status', 100)->default('pending')->change();
        });

        Schema::table('board_columns', function (Blueprint $table) {
            $table->string('status', 100)->change();
        });
    }

    private function restoreDefaultStatusColumns(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE tasks MODIFY status ENUM('pending','in-progress','completed') NOT NULL DEFAULT 'pending'");
            DB::statement("ALTER TABLE board_columns MODIFY status ENUM('pending','in-progress','completed') NOT NULL");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE tasks ALTER COLUMN status DROP DEFAULT");
            DB::statement("DROP TYPE IF EXISTS task_status_enum");
            DB::statement("CREATE TYPE task_status_enum AS ENUM ('pending', 'in-progress', 'completed')");
            DB::statement("ALTER TABLE tasks ALTER COLUMN status TYPE task_status_enum USING status::task_status_enum");
            DB::statement("ALTER TABLE tasks ALTER COLUMN status SET DEFAULT 'pending'");

            DB::statement("DROP TYPE IF EXISTS board_column_status_enum");
            DB::statement("CREATE TYPE board_column_status_enum AS ENUM ('pending', 'in-progress', 'completed')");
            DB::statement("ALTER TABLE board_columns ALTER COLUMN status TYPE board_column_status_enum USING status::board_column_status_enum");

            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('status', ['pending', 'in-progress', 'completed'])->default('pending')->change();
        });

        Schema::table('board_columns', function (Blueprint $table) {
            $table->enum('status', ['pending', 'in-progress', 'completed'])->change();
        });
    }

    private function seedDefaultBoardColumns(): void
    {
        $defaults = collect(BoardColumn::defaultColumns());
        $users = DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->pluck('id');

        $timestamp = now();

        foreach ($users as $userId) {
            $existing = DB::table('board_columns')
                ->where('user_id', $userId)
                ->get(['status', 'label'])
                ->keyBy('status');

            $rows = $defaults->map(function (array $column, int $index) use ($existing, $timestamp, $userId): array {
                return [
                    'user_id' => $userId,
                    'status' => $column['status'],
                    'label' => trim((string) ($existing[$column['status']]->label ?? $column['label'])),
                    'position' => $index + 1,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            })->all();

            DB::table('board_columns')->upsert(
                $rows,
                ['user_id', 'status'],
                ['label', 'position', 'updated_at'],
            );
        }
    }
};
