<?php

namespace App\Console\Commands;

use App\Models\AgentRun;
use Illuminate\Console\Command;

class PruneAgentRunPayloadsCommand extends Command
{
    protected $signature = 'agents:prune-run-payloads {--days= : Override the raw payload retention window in days}';

    protected $description = 'Remove old agent run context snapshots while retaining run and action audit metadata.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('ai.retention.raw_payload_days'));

        if ($days < 1) {
            $this->error('Retention days must be at least 1.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $count = AgentRun::query()
            ->whereNotNull('context_snapshot')
            ->where('created_at', '<', $cutoff)
            ->update([
                'context_snapshot' => null,
            ]);

        $this->info("Pruned {$count} agent run context snapshot(s).");

        return self::SUCCESS;
    }
}
