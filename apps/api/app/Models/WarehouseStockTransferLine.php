<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseStockTransferLine extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'transfer_id',
        'product_variant_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(WarehouseStockTransfer::class, 'transfer_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
