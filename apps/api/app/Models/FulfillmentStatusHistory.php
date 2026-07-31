<?php

namespace App\Models;

use App\Enums\FulfillmentStatusHistorySource;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentStatusHistory extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'fulfillment_id',
        'from_status',
        'to_status',
        'changed_by',
        'source',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'source' => FulfillmentStatusHistorySource::class,
        ];
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function changedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'changed_by');
    }
}
