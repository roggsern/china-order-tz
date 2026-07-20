<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Store extends Model
{
    use HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'slug',
        'description',
        'logo_path',
        'banner_path',
        'theme_color',
        'is_active',
        'storefront_enabled',
        'storefront_visible',
        'storefront_featured',
        'storefront_sort_order',
        'sort_order',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'storefront_enabled' => 'boolean',
            'storefront_visible' => 'boolean',
            'storefront_featured' => 'boolean',
            'storefront_sort_order' => 'integer',
            'sort_order' => 'integer',
            'settings' => 'array',
        ];
    }

    /**
     * Active stores exposed on the BUY FROM TZ public marketplace.
     */
    public function scopeStorefrontVisible(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('storefront_enabled', true)
            ->where('storefront_visible', true);
    }

    public function logoUrl(): ?string
    {
        if (! filled($this->logo_path)) {
            return null;
        }

        $path = (string) $this->logo_path;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url(ltrim($path, '/'));
    }

    public function bannerUrl(): ?string
    {
        if (! filled($this->banner_path)) {
            return null;
        }

        $path = (string) $this->banner_path;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url(ltrim($path, '/'));
    }

    public function inventoryLocations(): HasMany
    {
        return $this->hasMany(InventoryLocation::class);
    }

    public function defaultInventoryLocation(): HasOne
    {
        return $this->hasOne(InventoryLocation::class)->where('is_default', true);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function terminals(): HasMany
    {
        return $this->hasMany(PosTerminal::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StoreUserAssignment::class);
    }

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class, 'store_user_assignments')
            ->withPivot(['assignment_type', 'starts_at', 'ends_at', 'is_active', 'assigned_by'])
            ->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
