<?php

namespace App\DTOs\CMS;

use App\Enums\CMS\CmsCtaTargetType;
use App\Enums\CMS\CmsHeroContentAlignment;
use App\Enums\CMS\CmsHeroTextTheme;
use App\Enums\CMS\CmsStatus;
use Carbon\Carbon;

final class UpdateHeroSlideData
{
    /**
     * @param  array<string, true>  $present
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $headline = null,
        public readonly ?string $subheadline = null,
        public readonly ?string $eyebrowText = null,
        public readonly ?string $description = null,
        public readonly ?string $desktopMediaId = null,
        public readonly ?string $mobileMediaId = null,
        public readonly ?CmsHeroContentAlignment $contentAlignment = null,
        public readonly ?CmsHeroTextTheme $textTheme = null,
        public readonly ?string $primaryCtaLabel = null,
        public readonly ?CmsCtaTargetType $primaryCtaType = null,
        public readonly ?string $primaryCtaValue = null,
        public readonly ?string $secondaryCtaLabel = null,
        public readonly ?CmsCtaTargetType $secondaryCtaType = null,
        public readonly ?string $secondaryCtaValue = null,
        public readonly ?int $position = null,
        public readonly ?CmsStatus $status = null,
        public readonly ?bool $isVisible = null,
        public readonly ?Carbon $startsAt = null,
        public readonly ?Carbon $endsAt = null,
        public readonly array $present = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $keys = [
            'name', 'headline', 'subheadline', 'eyebrow_text', 'description',
            'desktop_media_id', 'mobile_media_id', 'content_alignment', 'text_theme',
            'primary_cta_label', 'primary_cta_type', 'primary_cta_value',
            'secondary_cta_label', 'secondary_cta_type', 'secondary_cta_value',
            'position', 'status', 'is_visible', 'starts_at', 'ends_at',
        ];
        $present = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                $present[$key] = true;
            }
        }

        return new self(
            name: array_key_exists('name', $payload) ? trim((string) $payload['name']) : null,
            headline: array_key_exists('headline', $payload) ? trim((string) $payload['headline']) : null,
            subheadline: array_key_exists('subheadline', $payload)
                ? self::nullableTrim($payload['subheadline'])
                : null,
            eyebrowText: array_key_exists('eyebrow_text', $payload)
                ? self::nullableTrim($payload['eyebrow_text'])
                : null,
            description: array_key_exists('description', $payload)
                ? self::nullableTrim($payload['description'])
                : null,
            desktopMediaId: array_key_exists('desktop_media_id', $payload)
                ? ($payload['desktop_media_id'] !== null ? (string) $payload['desktop_media_id'] : null)
                : null,
            mobileMediaId: array_key_exists('mobile_media_id', $payload)
                ? ($payload['mobile_media_id'] !== null ? (string) $payload['mobile_media_id'] : null)
                : null,
            contentAlignment: array_key_exists('content_alignment', $payload)
                ? CmsHeroContentAlignment::from((string) $payload['content_alignment'])
                : null,
            textTheme: array_key_exists('text_theme', $payload)
                ? CmsHeroTextTheme::from((string) $payload['text_theme'])
                : null,
            primaryCtaLabel: array_key_exists('primary_cta_label', $payload)
                ? self::nullableTrim($payload['primary_cta_label'])
                : null,
            primaryCtaType: array_key_exists('primary_cta_type', $payload)
                ? ($payload['primary_cta_type'] !== null
                    ? CmsCtaTargetType::from((string) $payload['primary_cta_type'])
                    : null)
                : null,
            primaryCtaValue: array_key_exists('primary_cta_value', $payload)
                ? self::nullableTrim($payload['primary_cta_value'])
                : null,
            secondaryCtaLabel: array_key_exists('secondary_cta_label', $payload)
                ? self::nullableTrim($payload['secondary_cta_label'])
                : null,
            secondaryCtaType: array_key_exists('secondary_cta_type', $payload)
                ? ($payload['secondary_cta_type'] !== null
                    ? CmsCtaTargetType::from((string) $payload['secondary_cta_type'])
                    : null)
                : null,
            secondaryCtaValue: array_key_exists('secondary_cta_value', $payload)
                ? self::nullableTrim($payload['secondary_cta_value'])
                : null,
            position: array_key_exists('position', $payload) ? (int) $payload['position'] : null,
            status: array_key_exists('status', $payload)
                ? CmsStatus::from((string) $payload['status'])
                : null,
            isVisible: array_key_exists('is_visible', $payload) ? (bool) $payload['is_visible'] : null,
            startsAt: array_key_exists('starts_at', $payload)
                ? ($payload['starts_at'] !== null ? Carbon::parse($payload['starts_at']) : null)
                : null,
            endsAt: array_key_exists('ends_at', $payload)
                ? ($payload['ends_at'] !== null ? Carbon::parse($payload['ends_at']) : null)
                : null,
            present: $present,
        );
    }

    public function has(string $key): bool
    {
        return isset($this->present[$key]);
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
