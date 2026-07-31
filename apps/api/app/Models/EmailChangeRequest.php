<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailChangeRequest extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'user_id',
        'old_email',
        'new_email',
        'token_hash',
        'expires_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->confirmed_at === null && $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
