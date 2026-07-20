<?php

namespace App\Enums;

enum TransportMode: string
{
    case Air = 'air';
    case Sea = 'sea';
    case Road = 'road';

    public function label(): string
    {
        return match ($this) {
            self::Air => 'Air',
            self::Sea => 'Sea',
            self::Road => 'Road',
        };
    }
}
