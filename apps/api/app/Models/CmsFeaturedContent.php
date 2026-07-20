<?php

namespace App\Models;

use App\Enums\CMS\CmsFeaturedDisplayStyle;
use App\Enums\CMS\CmsFeaturedSourceType;
use App\Enums\CMS\CmsStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\CmsFeaturedContentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsFeaturedContent extends Model
{
    /** @use HasFactory<CmsFeaturedContentFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'cms_homepage_section_id',
        'title',
        'subtitle',
        'source_type',
        'limit',
        'sort_order',
        'display_style',
        'configuration',
        'position',
        'status',
        'is_visible',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => CmsFeaturedSourceType::class,
            'display_style' => CmsFeaturedDisplayStyle::class,
            'status' => CmsStatus::class,
            'configuration' => 'array',
            'limit' => 'integer',
            'position' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(CmsHomepageSection::class, 'cms_homepage_section_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * @param  Builder<CmsFeaturedContent>  $query
     * @return Builder<CmsFeaturedContent>
     */
    public function scopeStorefrontEligible(Builder $query): Builder
    {
        return $query
            ->where('status', CmsStatus::Active->value)
            ->where('is_visible', true);
    }
}
