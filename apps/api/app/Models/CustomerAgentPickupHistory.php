<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable Customer Agent pickup history. Never updated or deleted by application code.
 */
class CustomerAgentPickupHistory extends Model
{
    use HasUuidPrimaryKey;

    public $timestamps = false;

    protected $fillable = [
        'customer_agent_pickup_id',
        'order_id',
        'admin_id',
        'action',
        'from_status',
        'to_status',
        'reason',
        'metadata',
        'idempotency_key',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function pickup(): BelongsTo
    {
        return $this->belongsTo(CustomerAgentPickup::class, 'customer_agent_pickup_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
