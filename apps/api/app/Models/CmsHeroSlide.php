<?php

namespace App\Models;

use App\Enums\CMS\CmsCtaTargetType;
use App\Enums\CMS\CmsHeroContentAlignment;
use App\Enums\CMS\CmsHeroTextTheme;
use App\Enums\CMS\CmsStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Carbon\CarbonInterface;
use Database\Factories\CmsHeroSlideFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsHeroSlide extends Model
{
    /** @use HasFactory<CmsHeroSlideFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'cms_homepage_section_id',
        'name',
        'headline',
        'subheadline',
        'eyebrow_text',
        'description',
        'desktop_media_id',
        'mobile_media_id',
        'content_alignment',
        'text_theme',
        'primary_cta_label',
        'primary_cta_type',
        'primary_cta_value',
        'secondary_cta_label',
        'secondary_cta_type',
        'secondary_cta_value',
        'position',
        'status',
        'is_visible',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'content_alignment' => CmsHeroContentAlignment::class,
            'text_theme' => CmsHeroTextTheme::class,
            'primary_cta_type' => CmsCtaTargetType::class,
            'secondary_cta_type' => CmsCtaTargetType::class,
            'status' => CmsStatus::class,
            'position' => 'integer',
            'is_visible' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(CmsHomepageSection::class, 'cms_homepage_section_id');
    }

    public function desktopMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'desktop_media_id');
    }

    public function mobileMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'mobile_media_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * Storefront-eligible: ACTIVE + visible + within schedule (UTC / app timezone).
     *
     * @param  Builder<CmsHeroSlide>  $query
     * @return Builder<CmsHeroSlide>
     */
    public function scopeStorefrontEligible(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where('status', CmsStatus::Active->value)
            ->where('is_visible', true)
            ->where(function (Builder $q) use ($at) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function (Builder $q) use ($at) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', $at);
            });
    }
}
