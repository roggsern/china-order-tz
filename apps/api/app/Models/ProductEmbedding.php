<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductEmbedding extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'product_id',
        'embedding_model',
        'embedding',
        'source',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
