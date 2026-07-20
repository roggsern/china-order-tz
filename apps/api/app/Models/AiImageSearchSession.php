<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiImageSearchSession extends Model
{
    use HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'user_id',
        'image_path',
        'status',
        'results',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'results' => 'array',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
