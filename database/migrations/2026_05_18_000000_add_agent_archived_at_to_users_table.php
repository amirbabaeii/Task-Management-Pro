<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('agent_archived_at')
                ->nullable()
                ->after('agent_skills');

            $table->index(
                ['agent_manager_id', 'is_agent', 'agent_archived_at'],
                'users_agent_manager_archive_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_agent_manager_archive_index');
            $table->dropColumn('agent_archived_at');
        });
    }
};
