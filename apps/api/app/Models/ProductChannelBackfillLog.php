<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductChannelBackfillLog extends Model
{
    use HasUuidPrimaryKey;

    public const ACTION_ASSIGNED = 'assigned';

    public const ACTION_SKIPPED = 'skipped';

    public const ACTION_ROLLED_BACK = 'rolled_back';

    protected $fillable = [
        'batch_id',
        'product_id',
        'previous_channel_id',
        'assigned_channel_id',
        'action',
        'reason',
        'executed_at',
        'rolled_back',
        'rolled_back_at',
    ];

    protected function casts(): array
    {
        return [
            'executed_at' => 'datetime',
            'rolled_back' => 'boolean',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function previousChannel(): BelongsTo
    {
        return $this->belongsTo(CommerceChannel::class, 'previous_channel_id');
    }

    public function assignedChannel(): BelongsTo
    {
        return $this->belongsTo(CommerceChannel::class, 'assigned_channel_id');
    }
}
