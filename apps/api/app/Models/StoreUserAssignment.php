<?php

namespace App\Models;

use App\Enums\StoreAssignmentType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreUserAssignment extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'admin_id',
        'store_id',
        'assignment_type',
        'starts_at',
        'ends_at',
        'is_active',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'assignment_type' => StoreAssignmentType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function assignedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_by');
    }
}
