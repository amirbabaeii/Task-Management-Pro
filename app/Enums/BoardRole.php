<?php

namespace App\Enums;

enum BoardRole: string
{
    case Owner = 'owner';
    case Collaborator = 'collaborator';

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
            self::Owner => 'Owner',
            self::Collaborator => 'Collaborator',
        };
    }
}
