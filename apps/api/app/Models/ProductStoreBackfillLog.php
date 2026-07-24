<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStoreBackfillLog extends Model
{
    use HasUuidPrimaryKey;

    public const ACTION_ASSIGNED = 'assigned';

    public const ACTION_SKIPPED = 'skipped';

    public const ACTION_ROLLED_BACK = 'rolled_back';

    protected $fillable = [
        'batch_id',
        'product_id',
        'previous_store_id',
        'assigned_store_id',
        'action',
        'reason',
        'lifecycle_status',
        'rolled_back',
        'rolled_back_at',
    ];

    protected function casts(): array
    {
        return [
            'rolled_back' => 'boolean',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function previousStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'previous_store_id');
    }

    public function assignedStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'assigned_store_id');
    }
}
