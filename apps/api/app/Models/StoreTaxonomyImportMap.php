<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps a China source category to a TZ store-owned category for taxonomy import.
 */
class StoreTaxonomyImportMap extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'store_id',
        'source_category_id',
        'target_category_id',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function sourceCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'source_category_id');
    }

    public function targetCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'target_category_id');
    }
}
