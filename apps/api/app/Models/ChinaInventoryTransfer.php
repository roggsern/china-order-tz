<?php

namespace App\Models;

use App\Enums\ChinaInventoryTransferStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChinaInventoryTransfer extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'transfer_number',
        'status',
        'created_by',
        'notes',
        'received_china_at',
        'quality_checked_at',
        'ready_for_export_at',
        'shipped_at',
        'in_transit_at',
        'arrived_tanzania_at',
        'received_tanzania_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ChinaInventoryTransferStatus::class,
            'received_china_at' => 'datetime',
            'quality_checked_at' => 'datetime',
            'ready_for_export_at' => 'datetime',
            'shipped_at' => 'datetime',
            'in_transit_at' => 'datetime',
            'arrived_tanzania_at' => 'datetime',
            'received_tanzania_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ChinaInventoryTransferLine::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
