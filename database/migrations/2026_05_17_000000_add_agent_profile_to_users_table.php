<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_agent')->default(false)->after('password');
            $table->foreignId('agent_manager_id')
                ->nullable()
                ->after('is_agent')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('agent_title', 120)->nullable()->after('agent_manager_id');
            $table->text('agent_profile')->nullable()->after('agent_title');
            $table->text('agent_personality')->nullable()->after('agent_profile');
            $table->json('agent_skills')->nullable()->after('agent_personality');

            $table->index(['agent_manager_id', 'is_agent']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['agent_manager_id', 'is_agent']);
            $table->dropConstrainedForeignId('agent_manager_id');
            $table->dropColumn([
                'is_agent',
                'agent_title',
                'agent_profile',
                'agent_personality',
                'agent_skills',
            ]);
        });
    }
};
