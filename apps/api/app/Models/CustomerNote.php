<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerNote extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'customer_profile_id',
        'created_by',
        'body',
        'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_profile_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
