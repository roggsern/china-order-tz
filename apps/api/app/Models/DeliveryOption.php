<?php

namespace App\Models;

use App\Enums\DeliveryOptionStatus;
use App\Enums\DeliveryShippingMethod;
use App\Enums\DeliveryType;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\DeliveryOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryOption extends Model
{
    /** @use HasFactory<DeliveryOptionFactory> */
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'order_id',
        'delivery_type',
        'shipping_method',
        'delivery_status',
        'agent_name',
        'agent_contact',
        'agent_company',
        'agent_phone',
        'agent_email',
        'pickup_reference',
        'notes',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'delivery_type' => DeliveryType::class,
            'shipping_method' => DeliveryShippingMethod::class,
            'delivery_status' => DeliveryOptionStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
