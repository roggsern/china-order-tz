<?php

namespace App\Models;

use App\Enums\WarehousePickListStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehousePickList extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'warehouse_job_id',
        'order_id',
        'picker_id',
        'status',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WarehousePickListStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function warehouseJob(): BelongsTo
    {
        return $this->belongsTo(WarehouseJob::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function picker(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'picker_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WarehousePickListLine::class, 'pick_list_id');
    }
}
