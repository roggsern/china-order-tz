<?php

namespace App\Models;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\CmsHomepageLayoutFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsHomepageLayout extends Model
{
    /** @use HasFactory<CmsHomepageLayoutFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'name',
        'slug',
        'commerce_context',
        'status',
        'is_default',
        'default_slot',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'commerce_context' => CmsCommerceContext::class,
            'status' => CmsStatus::class,
            'is_default' => 'boolean',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CmsHomepageSection::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function isArchived(): bool
    {
        return $this->status === CmsStatus::Archived;
    }

    public function isActive(): bool
    {
        return $this->status === CmsStatus::Active;
    }
}
