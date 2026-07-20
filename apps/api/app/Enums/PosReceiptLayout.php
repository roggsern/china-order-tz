<?php

namespace App\Enums;

enum PosReceiptLayout: string
{
    case Thermal80 = 'thermal_80';
    case A4 = 'a4';
    case Pdf = 'pdf';

    public function label(): string
    {
        return match ($this) {
            self::Thermal80 => '80mm Thermal',
            self::A4 => 'A4 Printable',
            self::Pdf => 'PDF',
        };
    }
}
