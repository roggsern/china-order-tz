<?php

namespace App\Models;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Carbon\CarbonInterface;
use Database\Factories\CmsCampaignFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CmsCampaign extends Model
{
    /** @use HasFactory<CmsCampaignFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'commerce_context',
        'status',
        'starts_at',
        'ends_at',
        'priority',
        'is_default',
        'default_slot',
        'cms_homepage_layout_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'commerce_context' => CmsCommerceContext::class,
            'status' => CmsStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'priority' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(CmsHomepageLayout::class, 'cms_homepage_layout_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function heroSlides(): BelongsToMany
    {
        return $this->belongsToMany(
            CmsHeroSlide::class,
            'cms_campaign_hero_slide',
            'cms_campaign_id',
            'cms_hero_slide_id',
        )->withPivot(['position'])->withTimestamps()->orderByPivot('position');
    }

    public function featuredContents(): BelongsToMany
    {
        return $this->belongsToMany(
            CmsFeaturedContent::class,
            'cms_campaign_featured_content',
            'cms_campaign_id',
            'cms_featured_content_id',
        )->withPivot(['position'])->withTimestamps()->orderByPivot('position');
    }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(
            Promotion::class,
            'cms_campaign_promotion',
            'cms_campaign_id',
            'promotion_id',
        )->withPivot(['position'])->withTimestamps()->orderByPivot('position');
    }

    public function navigationShells(): BelongsToMany
    {
        return $this->belongsToMany(
            CmsNavigationShell::class,
            'cms_campaign_navigation_shell',
            'cms_campaign_id',
            'cms_navigation_shell_id',
        )->withTimestamps();
    }

    /**
     * @param  Builder<CmsCampaign>  $query
     * @return Builder<CmsCampaign>
     */
    public function scopeStorefrontEligible(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where('status', CmsStatus::Active->value)
            ->where(function (Builder $q) use ($at) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function (Builder $q) use ($at) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', $at);
            });
    }
}
