<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentStatusHistory extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'order_id',
        'admin_id',
        'previous_status',
        'new_status',
        'source',
        'idempotency_key',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
