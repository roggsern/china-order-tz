<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSearchLog extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'user_id',
        'session_id',
        'query',
        'locale',
        'result_count',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'result_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
