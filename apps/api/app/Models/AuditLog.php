<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'admin_id',
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'payload',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
