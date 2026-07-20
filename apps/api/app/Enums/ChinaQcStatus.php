<?php

namespace App\Enums;

enum ChinaQcStatus: string
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';
    case Hold = 'hold';
    case Reinspection = 'reinspection';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending inspection',
            self::Passed => 'Passed',
            self::Failed => 'Failed',
            self::Hold => 'Hold',
            self::Reinspection => 'Reinspection',
        };
    }
}
