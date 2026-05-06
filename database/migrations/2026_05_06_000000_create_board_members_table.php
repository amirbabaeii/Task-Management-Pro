<?php

use App\Enums\BoardRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['board_id', 'user_id']);
            $table->index('user_id');
        });

        // Backfill: every existing board's owner becomes a member with the
        // owner role and a backdated joined_at so accepted-membership checks
        // work the same way for legacy data.
        $timestamp = now();

        DB::table('boards')
            ->select(['id', 'user_id', 'created_at'])
            ->orderBy('id')
            ->lazy()
            ->each(function (object $board) use ($timestamp): void {
                DB::table('board_members')->insert([
                    'board_id' => $board->id,
                    'user_id' => $board->user_id,
                    'role' => BoardRole::Owner->value,
                    'joined_at' => $board->created_at ?? $timestamp,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_members');
    }
};
