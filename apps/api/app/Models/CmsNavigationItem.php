<?php

namespace App\Models;

use App\Enums\CMS\CmsCtaTargetType;
use App\Enums\CMS\CmsNavigationItemType;
use App\Enums\CMS\CmsNavigationVisibility;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\CmsNavigationItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsNavigationItem extends Model
{
    /** @use HasFactory<CmsNavigationItemFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'navigation_shell_id',
        'parent_id',
        'title',
        'icon',
        'position',
        'visibility',
        'item_type',
        'target_type',
        'target_value',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => CmsNavigationVisibility::class,
            'item_type' => CmsNavigationItemType::class,
            'target_type' => CmsCtaTargetType::class,
            'position' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }

    public function shell(): BelongsTo
    {
        return $this->belongsTo(CmsNavigationShell::class, 'navigation_shell_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('position')
            ->orderBy('id');
    }
}
