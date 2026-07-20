<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChinaOrderRequest extends Model
{
    use HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'user_id',
        'status',
        'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChinaOrderItem::class);
    }

    public function sourceLinks(): HasMany
    {
        return $this->hasMany(ChinaOrderSourceLink::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChinaOrderAttachment::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(ChinaOrderQuote::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ChinaOrderStatusHistory::class);
    }
}
