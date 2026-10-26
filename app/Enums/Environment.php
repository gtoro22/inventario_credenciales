<?php

namespace App\Enums;

enum Environment: string
{
    case Development = 'development';
    case Qa = 'qa';
    case Staging = 'staging';
    case Production = 'production';

    public function label(): string
    {
        return match ($this) {
            self::Development => 'Desarrollo',
            self::Qa => 'QA',
            self::Staging => 'Staging',
            self::Production => 'Producción',
        };
    }
}
