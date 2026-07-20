<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttributeDependency extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'product_type_id',
        'product_id',
        'source_attribute_id',
        'target_attribute_id',
    ];

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sourceAttribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'source_attribute_id');
    }

    public function targetAttribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'target_attribute_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(AttributeDependencyRule::class);
    }
}
