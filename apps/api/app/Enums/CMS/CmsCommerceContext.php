<?php

namespace App\Enums\CMS;

use App\Enums\CommerceChannelCode;

/**
 * CMS layout/section commerce scope.
 *
 * Reuses CHINA_IMPORT / TZ_LOCAL values from CommerceChannelCode.
 * GLOBAL is CMS-only shared content — not a commerce channel.
 */
enum CmsCommerceContext: string
{
    case Global = 'GLOBAL';
    case ChinaImport = 'CHINA_IMPORT';
    case TzLocal = 'TZ_LOCAL';

    public function label(): string
    {
        return match ($this) {
            self::Global => 'Global (shared)',
            self::ChinaImport => CommerceChannelCode::ChinaImport->label(),
            self::TzLocal => CommerceChannelCode::TzLocal->label(),
        };
    }

    public function toCommerceChannelCode(): ?CommerceChannelCode
    {
        return match ($this) {
            self::ChinaImport => CommerceChannelCode::ChinaImport,
            self::TzLocal => CommerceChannelCode::TzLocal,
            self::Global => null,
        };
    }

    public static function fromCommerceChannelCode(CommerceChannelCode $code): self
    {
        return match ($code) {
            CommerceChannelCode::ChinaImport => self::ChinaImport,
            CommerceChannelCode::TzLocal => self::TzLocal,
        };
    }

    /**
     * True when $source must not be wired under this layout context.
     */
    public function forbidsSource(self $source): bool
    {
        return match ($this) {
            self::ChinaImport => $source === self::TzLocal,
            self::TzLocal => $source === self::ChinaImport,
            self::Global => false,
        };
    }
}
