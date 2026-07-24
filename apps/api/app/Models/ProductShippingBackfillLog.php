<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductShippingBackfillLog extends Model
{
    use HasUuidPrimaryKey;

    public const ACTION_BACKFILLED = 'backfilled';

    public const ACTION_SKIPPED = 'skipped';

    public const ACTION_ROLLED_BACK = 'rolled_back';

    protected $fillable = [
        'batch_id',
        'product_id',
        'created_option_ids',
        'previous_state',
        'action',
        'reason',
        'executed_at',
        'rolled_back',
        'rolled_back_at',
    ];

    protected function casts(): array
    {
        return [
            'created_option_ids' => 'array',
            'previous_state' => 'array',
            'executed_at' => 'datetime',
            'rolled_back' => 'boolean',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
