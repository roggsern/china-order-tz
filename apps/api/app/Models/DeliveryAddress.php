<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\DeliveryAddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAddress extends Model
{
    /** @use HasFactory<DeliveryAddressFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'user_id',
        'recipient_name',
        'phone',
        'country',
        'region',
        'city',
        'district',
        'street',
        'landmark',
        'postal_code',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
