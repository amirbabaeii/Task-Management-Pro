<?php

namespace App\Enums;

enum AgentRunActionStatus: string
{
    case Suggested = 'suggested';
    case Proposed = 'proposed';
    case Applied = 'applied';
    case Rejected = 'rejected';
    case Failed = 'failed';

    public function isPendingApproval(): bool
    {
        return $this === self::Proposed;
    }
}
