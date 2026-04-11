<?php

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
        Schema::table('task_user', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('role');
        });

        $assignments = DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->select([
                'task_user.id',
                'task_user.user_id',
                'task_user.role',
                'tasks.status',
            ])
            ->orderBy('task_user.user_id')
            ->orderBy('task_user.role')
            ->orderBy('tasks.status')
            ->orderByDesc('tasks.updated_at')
            ->orderByDesc('tasks.id')
            ->get()
            ->groupBy(function (object $assignment): string {
                return implode(':', [
                    $assignment->user_id,
                    $assignment->role,
                    $assignment->status,
                ]);
            });

        foreach ($assignments as $group) {
            foreach ($group->values() as $index => $assignment) {
                DB::table('task_user')
                    ->where('id', $assignment->id)
                    ->update([
                        'sort_order' => $index + 1,
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_user', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
