<?php

namespace App\Enums;

enum AiProvider: string
{
    case OpenAI = 'openai';

    public function label(): string
    {
        return match ($this) {
            self::OpenAI => 'OpenAI',
        };
    }
}
