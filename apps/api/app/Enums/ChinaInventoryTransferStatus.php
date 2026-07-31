<?php

namespace App\Enums;

/**
 * China → Tanzania inventory transfer pipeline states.
 * Catalog sellable stock is only MAIN after ReceivedTanzania.
 */
enum ChinaInventoryTransferStatus: string
{
    case ReceivedChina = 'received_china';
    case QualityCheck = 'quality_check';
    case ReadyForExport = 'ready_for_export';
    case Shipment = 'shipment';
    case InTransit = 'in_transit';
    case ArrivedTanzania = 'arrived_tanzania';
    case ReceivedTanzania = 'received_tanzania';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ReceivedChina => 'China receiving',
            self::QualityCheck => 'Quality check',
            self::ReadyForExport => 'Ready for export',
            self::Shipment => 'Shipment allocated',
            self::InTransit => 'In transit',
            self::ArrivedTanzania => 'Arrived Tanzania',
            self::ReceivedTanzania => 'Tanzania receiving (sellable)',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::ReceivedTanzania, self::Cancelled], true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::ReceivedChina => [self::QualityCheck, self::Cancelled],
            self::QualityCheck => [self::ReadyForExport, self::Cancelled],
            self::ReadyForExport => [self::Shipment, self::Cancelled],
            self::Shipment => [self::InTransit, self::Cancelled],
            self::InTransit => [self::ArrivedTanzania, self::Cancelled],
            self::ArrivedTanzania => [self::ReceivedTanzania, self::Cancelled],
            self::ReceivedTanzania, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
