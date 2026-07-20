<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\CatalogAttributeOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogAttributeOption extends Model
{
    /** @use HasFactory<CatalogAttributeOptionFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'catalog_attribute_options';

    protected $fillable = [
        'catalog_attribute_id',
        'value',
        'slug',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(CatalogAttribute::class, 'catalog_attribute_id');
    }
}
