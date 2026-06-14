<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('manager_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('provider_connection_id')
                ->nullable()
                ->constrained('ai_provider_connections')
                ->nullOnDelete();
            $table->string('provider', 40);
            $table->string('model', 120);
            $table->string('autonomy', 40);
            $table->string('status', 40)->default('queued');
            $table->text('summary')->nullable();
            $table->text('rationale')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->string('provider_response_id', 255)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->json('context_snapshot')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'status']);
            $table->index(['manager_id', 'created_at']);
        });

        Schema::create('agent_run_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_run_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('type', 60);
            $table->string('status', 40);
            $table->json('payload');
            $table->text('error_message')->nullable();
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['agent_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_run_actions');
        Schema::dropIfExists('agent_runs');
    }
};
