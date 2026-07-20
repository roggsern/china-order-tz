<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserProfile extends Model
{
    use HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'user_id',
        'phone',
        'avatar',
        'preferred_language',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
