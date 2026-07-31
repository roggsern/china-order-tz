<?php

namespace App\Models;

use App\Enums\WarehousePackingStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehousePackingRecord extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'warehouse_job_id',
        'packer_id',
        'status',
        'package_status',
        'notes',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WarehousePackingStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function warehouseJob(): BelongsTo
    {
        return $this->belongsTo(WarehouseJob::class);
    }

    public function packer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'packer_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WarehousePackingLine::class, 'packing_record_id');
    }
}
