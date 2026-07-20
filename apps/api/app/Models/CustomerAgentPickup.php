<?php

namespace App\Models;

use App\Enums\AgentPickupStatus;
use App\Enums\PickupAuthorizationStatus;
use App\Enums\WarehouseReleaseStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerAgentPickup extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'order_id',
        'fulfillment_id',
        'warehouse_job_id',
        'delivery_option_id',
        'agent_name',
        'agent_company',
        'agent_phone',
        'agent_email',
        'agent_contact',
        'pickup_reference',
        'authorization_status',
        'authorization_expires_at',
        'authorized_at',
        'authorized_by',
        'authorization_notes',
        'rejected_at',
        'rejection_reason',
        'revoked_at',
        'revoke_reason',
        'release_status',
        'pickup_scheduled_at',
        'picked_up_at',
        'released_at',
        'release_operator_id',
        'release_notes',
        'pickup_status',
        'agent_arrived_at',
        'identity_verified_at',
        'authorization_verified_at',
        'goods_verified_at',
        'handover_completed_at',
        'handover_operator_id',
        'evidence',
        'pickup_notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'authorization_status' => PickupAuthorizationStatus::class,
            'release_status' => WarehouseReleaseStatus::class,
            'pickup_status' => AgentPickupStatus::class,
            'authorization_expires_at' => 'datetime',
            'authorized_at' => 'datetime',
            'rejected_at' => 'datetime',
            'revoked_at' => 'datetime',
            'pickup_scheduled_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'released_at' => 'datetime',
            'agent_arrived_at' => 'datetime',
            'identity_verified_at' => 'datetime',
            'authorization_verified_at' => 'datetime',
            'goods_verified_at' => 'datetime',
            'handover_completed_at' => 'datetime',
            'evidence' => 'array',
            'metadata' => 'array',
        ];
    }

    public function hasValidAuthorization(?\DateTimeInterface $at = null): bool
    {
        $this->refreshAuthorizationExpiry($at);

        return $this->authorization_status === PickupAuthorizationStatus::Authorized
            && ($this->authorization_expires_at === null || $this->authorization_expires_at->isFuture());
    }

    public function refreshAuthorizationExpiry(?\DateTimeInterface $at = null): void
    {
        if ($this->authorization_status !== PickupAuthorizationStatus::Authorized) {
            return;
        }

        if ($this->authorization_expires_at === null) {
            return;
        }

        $now = $at !== null ? \Illuminate\Support\Carbon::parse($at) : now();
        if ($this->authorization_expires_at->lte($now)) {
            $this->forceFill([
                'authorization_status' => PickupAuthorizationStatus::Expired,
            ])->save();
        }
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function warehouseJob(): BelongsTo
    {
        return $this->belongsTo(WarehouseJob::class);
    }

    public function deliveryOption(): BelongsTo
    {
        return $this->belongsTo(DeliveryOption::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(CustomerAgentPickupHistory::class)->orderBy('created_at');
    }
}
