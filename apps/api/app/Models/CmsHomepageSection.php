<?php

namespace App\Models;

use App\Enums\CMS\CmsHomepageSectionType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\CmsHomepageSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsHomepageSection extends Model
{
    /** @use HasFactory<CmsHomepageSectionFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'cms_homepage_layout_id',
        'section_type',
        'title',
        'subtitle',
        'position',
        'is_visible',
        'configuration',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'section_type' => CmsHomepageSectionType::class,
            'position' => 'integer',
            'is_visible' => 'boolean',
            'configuration' => 'array',
        ];
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(CmsHomepageLayout::class, 'cms_homepage_layout_id');
    }

    public function heroSlides(): HasMany
    {
        return $this->hasMany(CmsHeroSlide::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    public function featuredContents(): HasMany
    {
        return $this->hasMany(CmsFeaturedContent::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
