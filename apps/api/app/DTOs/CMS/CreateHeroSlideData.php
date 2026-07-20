<?php

namespace App\DTOs\CMS;

use App\Enums\CMS\CmsCtaTargetType;
use App\Enums\CMS\CmsHeroContentAlignment;
use App\Enums\CMS\CmsHeroTextTheme;
use App\Enums\CMS\CmsStatus;
use Carbon\Carbon;

final class CreateHeroSlideData
{
    public function __construct(
        public readonly string $name,
        public readonly string $headline,
        public readonly ?string $subheadline,
        public readonly ?string $eyebrowText,
        public readonly ?string $description,
        public readonly ?string $desktopMediaId,
        public readonly ?string $mobileMediaId,
        public readonly CmsHeroContentAlignment $contentAlignment,
        public readonly CmsHeroTextTheme $textTheme,
        public readonly ?string $primaryCtaLabel,
        public readonly ?CmsCtaTargetType $primaryCtaType,
        public readonly ?string $primaryCtaValue,
        public readonly ?string $secondaryCtaLabel,
        public readonly ?CmsCtaTargetType $secondaryCtaType,
        public readonly ?string $secondaryCtaValue,
        public readonly int $position,
        public readonly CmsStatus $status,
        public readonly bool $isVisible,
        public readonly ?Carbon $startsAt,
        public readonly ?Carbon $endsAt,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: trim((string) $payload['name']),
            headline: trim((string) $payload['headline']),
            subheadline: self::nullableTrim($payload['subheadline'] ?? null),
            eyebrowText: self::nullableTrim($payload['eyebrow_text'] ?? null),
            description: self::nullableTrim($payload['description'] ?? null),
            desktopMediaId: isset($payload['desktop_media_id']) ? (string) $payload['desktop_media_id'] : null,
            mobileMediaId: isset($payload['mobile_media_id']) ? (string) $payload['mobile_media_id'] : null,
            contentAlignment: isset($payload['content_alignment'])
                ? CmsHeroContentAlignment::from((string) $payload['content_alignment'])
                : CmsHeroContentAlignment::Center,
            textTheme: isset($payload['text_theme'])
                ? CmsHeroTextTheme::from((string) $payload['text_theme'])
                : CmsHeroTextTheme::Light,
            primaryCtaLabel: self::nullableTrim($payload['primary_cta_label'] ?? null),
            primaryCtaType: isset($payload['primary_cta_type'])
                ? CmsCtaTargetType::from((string) $payload['primary_cta_type'])
                : null,
            primaryCtaValue: self::nullableTrim($payload['primary_cta_value'] ?? null),
            secondaryCtaLabel: self::nullableTrim($payload['secondary_cta_label'] ?? null),
            secondaryCtaType: isset($payload['secondary_cta_type'])
                ? CmsCtaTargetType::from((string) $payload['secondary_cta_type'])
                : null,
            secondaryCtaValue: self::nullableTrim($payload['secondary_cta_value'] ?? null),
            position: (int) ($payload['position'] ?? 0),
            status: isset($payload['status'])
                ? CmsStatus::from((string) $payload['status'])
                : CmsStatus::Draft,
            isVisible: (bool) ($payload['is_visible'] ?? true),
            startsAt: isset($payload['starts_at']) && $payload['starts_at'] !== null
                ? Carbon::parse($payload['starts_at'])
                : null,
            endsAt: isset($payload['ends_at']) && $payload['ends_at'] !== null
                ? Carbon::parse($payload['ends_at'])
                : null,
        );
    }

    private static function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
