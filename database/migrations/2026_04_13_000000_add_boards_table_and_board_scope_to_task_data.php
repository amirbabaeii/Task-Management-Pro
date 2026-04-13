<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();

            $table->index(['user_id', 'position']);
        });

        Schema::table('board_columns', function (Blueprint $table) {
            $table->foreignId('board_id')
                ->nullable()
                ->after('user_id')
                ->constrained('boards')
                ->cascadeOnDelete();
        });

        Schema::table('task_user', function (Blueprint $table) {
            $table->foreignId('board_id')
                ->nullable()
                ->after('user_id')
                ->constrained('boards')
                ->nullOnDelete();
        });

        $timestamp = now();

        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->lazy()
            ->each(function (object $user) use ($timestamp): void {
                DB::table('boards')->insert([
                    'user_id' => $user->id,
                    'name' => 'My Board',
                    'position' => 1,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            });

        $boardIdsByUser = DB::table('boards')
            ->select(['id', 'user_id'])
            ->orderBy('user_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (object $board): array => [
                (int) $board->user_id => (int) $board->id,
            ]);

        foreach ($boardIdsByUser as $userId => $boardId) {
            DB::table('board_columns')
                ->where('user_id', $userId)
                ->update([
                    'board_id' => $boardId,
                    'updated_at' => $timestamp,
                ]);

            DB::table('task_user')
                ->where('user_id', $userId)
                ->update([
                    'board_id' => $boardId,
                    'updated_at' => $timestamp,
                ]);
        }

        Schema::table('board_columns', function (Blueprint $table) {
            $table->index('user_id');
            $table->dropUnique('board_columns_user_id_status_unique');
            $table->unique(['board_id', 'status']);
            $table->index(['board_id', 'position']);
        });

        Schema::table('task_user', function (Blueprint $table) {
            $table->index(['user_id', 'board_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('task_user', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'board_id', 'role']);
        });

        Schema::table('board_columns', function (Blueprint $table) {
            $table->dropUnique(['board_id', 'status']);
            $table->dropIndex(['board_id', 'position']);
            $table->unique(['user_id', 'status']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('task_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('board_id');
        });

        Schema::table('board_columns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('board_id');
        });

        Schema::dropIfExists('boards');
    }
};
