<?php

namespace App\Enums;

enum AgentRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case AwaitingApproval = 'awaiting_approval';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [
            self::Queued->value,
            self::Running->value,
            self::AwaitingApproval->value,
        ];
    }

    public function isActive(): bool
    {
        return in_array($this, [
            self::Queued,
            self::Running,
            self::AwaitingApproval,
        ], true);
    }
}
