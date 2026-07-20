<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributeDependencyRule extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'attribute_dependency_id',
        'source_attribute_value_id',
        'target_attribute_value_id',
    ];

    public function dependency(): BelongsTo
    {
        return $this->belongsTo(AttributeDependency::class, 'attribute_dependency_id');
    }

    public function sourceValue(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeValue::class, 'source_attribute_value_id');
    }

    public function targetValue(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeValue::class, 'target_attribute_value_id');
    }
}
