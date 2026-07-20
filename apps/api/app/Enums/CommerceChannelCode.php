<?php

namespace App\Enums;

enum CommerceChannelCode: string
{
    case ChinaImport = 'CHINA_IMPORT';
    case TzLocal = 'TZ_LOCAL';

    public function label(): string
    {
        return match ($this) {
            self::ChinaImport => 'Order From China',
            self::TzLocal => 'Buy From Tanzania',
        };
    }

    public function customerSourceLabel(): string
    {
        return match ($this) {
            self::ChinaImport => 'Imported From China',
            self::TzLocal => 'Available In Tanzania',
        };
    }

    /** Legacy products.fulfillment_source value kept in sync for existing engines. */
    public function fulfillmentSource(): string
    {
        return match ($this) {
            self::ChinaImport => 'imported_from_china',
            self::TzLocal => 'buy_from_tz',
        };
    }

    public static function fromFulfillmentSource(?string $source): self
    {
        return match (strtolower((string) $source)) {
            'buy_from_tz' => self::TzLocal,
            default => self::ChinaImport,
        };
    }
}
