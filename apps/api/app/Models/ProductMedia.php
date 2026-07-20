<?php

namespace App\Models;

use App\Enums\ProductMediaType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\ProductMediaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductMedia extends Model
{
    /** @use HasFactory<ProductMediaFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'product_media';

    protected $fillable = [
        'product_id',
        'type',
        'url',
        'thumbnail_url',
        'alt_text',
        'title',
        'sort_order',
        'is_primary',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductMediaType::class,
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeImages(Builder $query): Builder
    {
        return $query->where('type', ProductMediaType::Image);
    }

    public function scopeVideos(Builder $query): Builder
    {
        return $query->where('type', ProductMediaType::Video);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('created_at');
    }
}
