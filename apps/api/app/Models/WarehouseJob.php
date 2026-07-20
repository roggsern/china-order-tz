<?php

namespace App\Models;

use App\Enums\WarehouseJobStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\WarehouseJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseJob extends Model
{
    /** @use HasFactory<WarehouseJobFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'order_id',
        'fulfillment_id',
        'job_number',
        'status',
        'picker_id',
        'packer_id',
        'picked_at',
        'packed_at',
        'ready_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => WarehouseJobStatus::class,
            'picked_at' => 'datetime',
            'packed_at' => 'datetime',
            'ready_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function picker(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'picker_id');
    }

    public function packer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'packer_id');
    }
}
