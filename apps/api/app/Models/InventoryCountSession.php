<?php

namespace App\Models;

use App\Enums\InventoryCountScope;
use App\Enums\InventoryCountStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCountSession extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'count_number', 'store_id', 'inventory_location_id', 'scope', 'category_id',
        'status', 'notes', 'created_by', 'approved_by',
        'started_at', 'submitted_at', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'scope' => InventoryCountScope::class,
            'status' => InventoryCountStatus::class,
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryCountLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }
}
