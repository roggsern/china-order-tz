<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'order_status_history';

    protected $fillable = [
        'order_id',
        'changed_by_admin_id',
        'changed_by_user_id',
        'previous_status',
        'new_status',
        'notes',
        'source',
        'actor_type',
        'metadata',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function changedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'changed_by_admin_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
