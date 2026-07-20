<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRecommendation extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'user_id',
        'session_id',
        'recommendation_type',
        'product_ids',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'product_ids' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
