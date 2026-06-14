<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('agent_provider_connection_id')
                ->nullable()
                ->after('agent_manager_id')
                ->constrained('ai_provider_connections')
                ->nullOnDelete();
            $table->string('agent_model', 120)
                ->nullable()
                ->after('agent_provider_connection_id');
            $table->string('agent_autonomy', 40)
                ->default('approval')
                ->after('agent_model');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_provider_connection_id');
            $table->dropColumn([
                'agent_model',
                'agent_autonomy',
            ]);
        });
    }
};
