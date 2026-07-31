<?php

namespace App\Enums;

enum ChinaProcurementRequirementStatus: string
{
    case Pending = 'pending';
    case Purchasing = 'purchasing';
    case Purchased = 'purchased';
    case QcPending = 'qc_pending';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Purchase',
            self::Purchasing => 'Purchasing',
            self::Purchased => 'Purchased',
            self::QcPending => 'QC Pending',
            self::Completed => 'Completed',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
