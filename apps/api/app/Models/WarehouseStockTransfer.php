<?php

namespace App\Models;

use App\Enums\WarehouseStockTransferStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseStockTransfer extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'transfer_number',
        'from_facility_id',
        'to_facility_id',
        'status',
        'requested_by_admin_id',
        'approved_by_admin_id',
        'notes',
        'requested_at',
        'approved_at',
        'transferred_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WarehouseStockTransferStatus::class,
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'transferred_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function fromFacility(): BelongsTo
    {
        return $this->belongsTo(WarehouseFacility::class, 'from_facility_id');
    }

    public function toFacility(): BelongsTo
    {
        return $this->belongsTo(WarehouseFacility::class, 'to_facility_id');
    }

    public function requestedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'requested_by_admin_id');
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WarehouseStockTransferLine::class, 'transfer_id');
    }
}
