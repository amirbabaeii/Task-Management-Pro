<?php

namespace App\Enums;

enum AgentAutonomy: string
{
    case Advisory = 'advisory';
    case Approval = 'approval';
    case Automatic = 'automatic';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Advisory => 'Advisory only',
            self::Approval => 'Approval required',
            self::Automatic => 'Scoped automatic actions',
        };
    }

    public function level(): int
    {
        return match ($this) {
            self::Advisory => 0,
            self::Approval => 1,
            self::Automatic => 2,
        };
    }

    public function allows(AgentAutonomy $requested): bool
    {
        return $requested->level() <= $this->level();
    }
}
