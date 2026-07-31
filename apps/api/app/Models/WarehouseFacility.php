<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseFacility extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'code',
        'name',
        'inventory_warehouse_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function zones(): HasMany
    {
        return $this->hasMany(WarehouseZone::class, 'facility_id');
    }
}
