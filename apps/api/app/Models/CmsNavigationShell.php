<?php

namespace App\Models;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsNavigationType;
use App\Enums\CMS\CmsStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\CmsNavigationShellFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsNavigationShell extends Model
{
    /** @use HasFactory<CmsNavigationShellFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'name',
        'slug',
        'commerce_context',
        'navigation_type',
        'status',
        'is_default',
        'default_slot',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'commerce_context' => CmsCommerceContext::class,
            'navigation_type' => CmsNavigationType::class,
            'status' => CmsStatus::class,
            'is_default' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CmsNavigationItem::class, 'navigation_shell_id')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function rootItems(): HasMany
    {
        return $this->hasMany(CmsNavigationItem::class, 'navigation_shell_id')
            ->whereNull('parent_id')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(
            CmsCampaign::class,
            'cms_campaign_navigation_shell',
            'cms_navigation_shell_id',
            'cms_campaign_id',
        )->withTimestamps();
    }

    public static function defaultSlotKey(CmsCommerceContext $context, CmsNavigationType $type): string
    {
        return $context->value.':'.$type->value;
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
