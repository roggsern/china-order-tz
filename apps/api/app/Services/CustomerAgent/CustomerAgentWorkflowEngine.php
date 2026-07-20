<?php

namespace App\Services\CustomerAgent;

use App\Enums\AgentPickupStatus;
use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\NotificationEventType;
use App\Enums\PickupAuthorizationStatus;
use App\Enums\WarehouseReleaseStatus;
use App\Events\CustomerAgent\AgentHandoverCompleted;
use App\Events\CustomerAgent\PickupAuthorized;
use App\Events\CustomerAgent\PickupCompleted;
use App\Events\CustomerAgent\PickupRejected;
use App\Events\CustomerAgent\PickupScheduled;
use App\Events\CustomerAgent\PickupStarted;
use App\Events\CustomerAgent\WarehouseReleased;
use App\Models\Admin;
use App\Models\CustomerAgentPickup;
use App\Models\CustomerAgentPickupHistory;
use App\Models\Order;
use App\Models\WarehouseJob;
use App\Services\China\ChinaWorkflowEngine;
use App\Services\Fulfillment\FulfillmentEngine;
use App\Services\Notifications\NotificationPlatform;
use App\Services\Shipments\ShipmentEligibilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Customer Agent Workflow — specialization of Model B logistics.
 *
 * NOT a second ShipmentEngine. Company transport remains ShipmentEngine.
 * NEVER writes orders.status (OrderLifecycleEngine via FulfillmentEngine sync).
 * NEVER calculates Export Ready (consumes ChinaWorkflowEngine).
 *
 * Ownership:
 * - Authorization → this engine (logistics)
 * - Warehouse release state → warehouse-owned field; transitions by warehouse roles
 * - Handover / pickup confirmation → this engine
 * - Company transport tracking → ShipmentEngine / TrackingEngine (not used here)
 */
class CustomerAgentWorkflowEngine
{
    public function __construct(
        private readonly ChinaWorkflowEngine $chinaWorkflow,
        private readonly ShipmentEligibilityService $eligibility,
        private readonly FulfillmentEngine $fulfillment,
        private readonly NotificationPlatform $notifications,
    ) {}

    public function showForOrder(Order $order): ?CustomerAgentPickup
    {
        return CustomerAgentPickup::query()
            ->with(['histories', 'warehouseJob', 'deliveryOption'])
            ->where('order_id', $order->id)
            ->first();
    }

    /**
     * Bootstrap pickup record for Customer Agent orders (idempotent).
     */
    public function bootstrap(Order $order, ?Admin $admin = null): CustomerAgentPickup
    {
        return DB::transaction(function () use ($order, $admin): CustomerAgentPickup {
            $existing = CustomerAgentPickup::query()->where('order_id', $order->id)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing->load(['histories']);
            }

            $order->loadMissing(['deliveryOption', 'fulfillment.warehouseJob']);
            $delivery = $order->deliveryOption;
            $type = $delivery?->delivery_type instanceof DeliveryType
                ? $delivery->delivery_type
                : DeliveryType::tryFrom((string) ($delivery?->delivery_type ?? ''));

            if ($type !== DeliveryType::CustomerAgent) {
                throw ValidationException::withMessages([
                    'delivery' => ['Customer Agent workflow only applies to Customer Agent delivery.'],
                ]);
            }

            $agentName = $delivery->agent_name;
            if (! filled($agentName)) {
                throw ValidationException::withMessages([
                    'agent_name' => ['Agent name is required before Customer Agent pickup can start.'],
                ]);
            }

            $reference = $delivery->pickup_reference
                ?: 'CAP-'.$order->order_number;

            $job = $order->fulfillment?->warehouseJob
                ?? WarehouseJob::query()->where('order_id', $order->id)->first();

            $pickup = CustomerAgentPickup::query()->create([
                'order_id' => $order->id,
                'fulfillment_id' => $order->fulfillment_id ?? $order->fulfillment?->id,
                'warehouse_job_id' => $job?->id,
                'delivery_option_id' => $delivery->id,
                'agent_name' => $agentName,
                'agent_company' => $delivery->agent_company,
                'agent_phone' => $delivery->agent_phone ?: $delivery->agent_contact,
                'agent_email' => $delivery->agent_email,
                'agent_contact' => $delivery->agent_contact,
                'pickup_reference' => $reference,
                'authorization_status' => PickupAuthorizationStatus::Pending,
                'pickup_status' => AgentPickupStatus::AwaitingPickup,
                'metadata' => [
                    'authority' => 'CustomerAgentWorkflowEngine',
                    'ownership' => 'customer_logistics_provider',
                ],
            ]);

            if ($delivery->pickup_reference === null) {
                $delivery->forceFill(['pickup_reference' => $reference])->save();
            }

            $this->writeHistory(
                $pickup,
                $admin,
                'bootstrapped',
                null,
                AgentPickupStatus::AwaitingPickup->value,
                'Customer Agent pickup record created',
                ['pickup_reference' => $reference],
                'agent-pickup-bootstrap:'.$order->id,
            );

            return $pickup->fresh(['histories']) ?? $pickup;
        });
    }

    /**
     * @param  array{expires_at?: string|null, notes?: string|null, agent_company?: string|null, agent_phone?: string|null, agent_email?: string|null}  $input
     */
    public function authorize(Order $order, Admin $admin, array $input = []): CustomerAgentPickup
    {
        return DB::transaction(function () use ($order, $admin, $input): CustomerAgentPickup {
            $pickup = $this->lockOrBootstrap($order, $admin);
            $key = 'agent-pickup-authorize:'.$order->id;

            if ($this->historyExists($key) && $pickup->authorization_status === PickupAuthorizationStatus::Authorized) {
                return $pickup;
            }

            $this->assertPickupPrerequisites($order, requireAuthorization: false);

            if (in_array($pickup->authorization_status, [
                PickupAuthorizationStatus::Rejected,
                PickupAuthorizationStatus::Revoked,
            ], true) && empty($input['reissue'])) {
                throw ValidationException::withMessages([
                    'authorization' => ['Authorization was '.$pickup->authorization_status->value.'. Reissue explicitly if needed.'],
                ]);
            }

            $from = $pickup->authorization_status->value;
            $expiresAt = isset($input['expires_at']) && filled($input['expires_at'])
                ? \Illuminate\Support\Carbon::parse((string) $input['expires_at'])
                : now()->addDays(7);

            $pickup->forceFill([
                'authorization_status' => PickupAuthorizationStatus::Authorized,
                'authorization_expires_at' => $expiresAt,
                'authorized_at' => now(),
                'authorized_by' => $admin->id,
                'authorization_notes' => $input['notes'] ?? $pickup->authorization_notes,
                'agent_company' => $input['agent_company'] ?? $pickup->agent_company,
                'agent_phone' => $input['agent_phone'] ?? $pickup->agent_phone,
                'agent_email' => $input['agent_email'] ?? $pickup->agent_email,
                'rejected_at' => null,
                'rejection_reason' => null,
                'revoked_at' => null,
                'revoke_reason' => null,
                'release_status' => $pickup->release_status ?? WarehouseReleaseStatus::ReadyForPickup,
            ])->save();

            $this->writeHistory(
                $pickup,
                $admin,
                'authorization_issued',
                $from,
                PickupAuthorizationStatus::Authorized->value,
                $input['notes'] ?? 'Pickup authorization issued',
                ['expires_at' => $expiresAt->toIso8601String()],
                $key,
            );

            event(new PickupAuthorized($pickup->id, $pickup->order_id, $admin->id));
            $this->safeNotifyCustomer($pickup, NotificationEventType::AgentPickupAuthorized, 'Pickup authorization issued');

            return $pickup->fresh(['histories']) ?? $pickup;
        });
    }

    public function rejectAuthorization(Order $order, Admin $admin, string $reason): CustomerAgentPickup
    {
        return DB::transaction(function () use ($order, $admin, $reason): CustomerAgentPickup {
            $pickup = $this->lockOrBootstrap($order, $admin);
            $key = 'agent-pickup-reject:'.$order->id.':'.md5($reason);

            if ($pickup->authorization_status === PickupAuthorizationStatus::Rejected) {
                return $pickup;
            }

            if ($pickup->handover_completed_at !== null) {
                throw ValidationException::withMessages([
                    'authorization' => ['Cannot reject authorization after handover is complete.'],
                ]);
            }

            $from = $pickup->authorization_status->value;
            $pickup->forceFill([
                'authorization_status' => PickupAuthorizationStatus::Rejected,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            $this->writeHistory(
                $pickup,
                $admin,
                'authorization_rejected',
                $from,
                PickupAuthorizationStatus::Rejected->value,
                $reason,
                [],
                $key,
            );

            event(new PickupRejected($pickup->id, $pickup->order_id, $admin->id));

            return $pickup->fresh(['histories']) ?? $pickup;
        });
    }

    public function revokeAuthorization(Order $order, Admin $admin, string $reason): CustomerAgentPickup
    {
        return DB::transaction(function () use ($order, $admin, $reason): CustomerAgentPickup {
            $pickup = $this->lockPickup($order);
            $key = 'agent-pickup-revoke:'.$order->id;

            if ($pickup->authorization_status === PickupAuthorizationStatus::Revoked) {
                return $pickup;
            }

            if ($pickup->handover_completed_at !== null) {
                throw ValidationException::withMessages([
                    'authorization' => ['Cannot revoke authorization after handover is complete.'],
                ]);
            }

            if ($pickup->release_status === WarehouseReleaseStatus::Released) {
                throw ValidationException::withMessages([
                    'authorization' => ['Cannot revoke after warehouse release.'],
                ]);
            }

            $from = $pickup->authorization_status->value;
            $pickup->forceFill([
                'authorization_status' => PickupAuthorizationStatus::Revoked,
                'revoked_at' => now(),
                'revoke_reason' => $reason,
            ])->save();

            $this->writeHistory(
                $pickup,
                $admin,
                'authorization_revoked',
                $from,
                PickupAuthorizationStatus::Revoked->value,
                $reason,
                [],
                $key,
            );

            $this->safeNotifyCustomer($pickup, NotificationEventType::AgentPickupAuthorizationRevoked, 'Pickup authorization revoked');

            return $pickup->fresh(['histories']) ?? $pickup;
        });
    }

    public function schedulePickup(Order $order, Admin $admin, ?\DateTimeInterface $scheduledAt = null, ?string $notes = null): CustomerAgentPickup
    {
        return DB::transaction(function () use ($order, $admin, $scheduledAt, $notes): CustomerAgentPickup {
            $pickup = $this->lockPickup($order);
            $this->assertAuthorizationValid($pickup);
            $this->assertPickupPrerequisites($order);

            $key = 'agent-pickup-schedule:'.$order->id;
            if ($pickup->pickup_status === AgentPickupStatus::PickupScheduled
                && $pickup->release_status === WarehouseReleaseStatus::PickupScheduled
                && $this->historyExists($key)
            ) {
                return $pickup;
            }

            $when = $scheduledAt !== null
                ? \Illuminate\Support\Carbon::parse($scheduledAt)
                : now()->addDay();

            $fromRelease = $pickup->release_status?->value;
            $this->transitionRelease($pickup, WarehouseReleaseStatus::PickupScheduled, $admin);

            $fromPickup = $pickup->pickup_status->value;
            $pickup->forceFill([
                'pickup_scheduled_at' => $when,
                'pickup_status' => AgentPickupStatus::PickupScheduled,
                'pickup_notes' => $notes ?? $pickup->pickup_notes,
            ])->save();

            $this->writeHistory(
                $pickup,
                $admin,
                'pickup_scheduled',
                $fromPickup,
                AgentPickupStatus::PickupScheduled->value,
                $notes ?? 'Pickup scheduled',
                [
                    'scheduled_at' => $when->toIso8601String(),
                    'release_from' => $fromRelease,
                ],
                $key,
            );

            event(new PickupScheduled($pickup->id, $pickup->order_id, $admin->id));
            $this->safeNotifyCustomer($pickup, NotificationEventType::AgentPickupScheduled, 'Pickup scheduled');

            return $pickup->fresh(['histories']) ?? $pickup;
        });
    }

    /**
     * Warehouse-owned release transition. Customer cannot call this.
     */
    public function transitionWarehouseRelease(
        Order $order,
        Admin $admin,
        WarehouseReleaseStatus $to,
        ?string $notes = null,
    ): CustomerAgentPickup {
        return DB::transaction(function () use ($order, $admin, $to, $notes): CustomerAgentPickup {
            $pickup = $this->lockPickup($order);
            $this->assertAuthorizationValid($pickup);
            $this->assertPickupPrerequisites($order);

            $key = 'agent-warehouse-release:'.$order->id.':'.$to->value;
            if ($pickup->release_status === $to && $this->historyExists($key)) {
                return $pickup;
            }

            $from = $pickup->release_status?->value;
            $this->transitionRelease($pickup, $to, $admin, $notes);

            if ($to === WarehouseReleaseStatus::PickedUp) {
                $pickup->forceFill([
                    'picked_up_at' => $pickup->picked_up_at ?? now(),
                    'pickup_status' => AgentPickupStatus::AgentArrived,
                ])->save();
            }

            if ($to === WarehouseReleaseStatus::Released) {
                $pickup->forceFill([
                    'released_at' => now(),
                    'release_operator_id' => $admin->id,
                ])->save();

                $this->advanceFulfillmentTowardReleased($order);
            }

            if ($to === WarehouseReleaseStatus::FailedPickup) {
                $pickup->forceFill(['pickup_status' => AgentPickupStatus::Failed])->save();
            }

            if ($to === WarehouseReleaseStatus::Reattempt) {
                $pickup->forceFill(['pickup_status' => AgentPickupStatus::AwaitingPickup])->save();
            }

            if ($to === WarehouseReleaseStatus::Cancelled) {
                $pickup->forceFill(['pickup_status' => AgentPickupStatus::Cancelled])->save();
            }

            $this->writeHistory(
                $pickup,
                $admin,
                'warehouse_release_'.$to->value,
                $from,
                $to->value,
                $notes ?? 'Warehouse release status updated',
                ['release_status' => $to->value],
                $key,
            );

            if ($to === WarehouseReleaseStatus::Released) {
                event(new WarehouseReleased($pickup->id, $pickup->order_id, $admin->id));
                $this->safeNotifyCustomer($pickup, NotificationEventType::AgentWarehouseReleased, 'Warehouse released to agent');
            }

            if ($to === WarehouseReleaseStatus::ReadyForPickup) {
                $this->safeNotifyCustomer($pickup, NotificationEventType::AgentPickupReady, 'Ready for agent pickup');
            }

            return $pickup->fresh(['histories']) ?? $pickup;
        });
    }

    public function recordAgentArrived(Order $order, Admin $admin, ?string $notes = null): CustomerAgentPickup
    {
        return DB::transaction(function () use ($order, $admin, $notes): CustomerAgentPickup {
            $pickup = $this->lockPickup($order);
            $this->assertAuthorizationValid($pickup);
            $key = 'agent-arrived:'.$order->id;

            if ($pickup->agent_arrived_at !== null && $this->historyExists($key)) {
                return $pickup;
            }

            $from = $pickup->pickup_status->value;
            $pickup->forceFill([
                'agent_arrived_at' => now(),
                'pickup_status' => AgentPickupStatus::AgentArrived,
                'pickup_notes' => $notes ?? $pickup->pickup_notes,
            ])->save();

            $this->writeHistory($pickup, $admin, 'agent_arrived', $from, AgentPickupStatus::AgentArrived->value, $notes, [], $key);

            event(new PickupStarted($pickup->id, $pickup->order_id, $admin->id));

            return $pickup->fresh(['histories']) ?? $pickup;
        });
    }

    public function verifyIdentity(Order $order, Admin $admin, ?string $notes = null): CustomerAgentPickup
    {
        return $this->verifyStep($order, $admin, 'identity_verified_at', AgentPickupStatus::IdentityVerified, 'identity_verified', $notes);
    }

    public function verifyAuthorizationAtPickup(Order $order, Admin $admin, ?string $notes = null): CustomerAgentPickup
    {
        return DB::transaction(function () use ($order, $admin, $notes): CustomerAgentPickup {
            $pickup = $this->lockPickup($order);
            $this->assertAuthorizationValid($pickup);

            return $this->verifyStep($order, $admin, 'authorization_verified_at', AgentPickupStatus::AuthorizationVerified, 'authorization_verified', $notes);
        });
    }

    public function verifyGoods(Order $order, Admin $admin, ?string $notes = null): CustomerAgentPickup
    {
        return $this->verifyStep($order, $admin, 'goods_verified_at', AgentPickupStatus::GoodsVerified, 'goods_verified', $notes);
    }

    /**
     * Complete handover with evidence. Ends company logistics responsibility.
     *
     * @param  array{
     *     signature?: string|null,
     *     reference_number?: string|null,
     *     document_number?: string|null,
     *     photos?: list<string>|null,
     *     notes?: string|null,
     *     metadata?: array<string, mixed>|null,
     *     agent_name?: string|null,
     *     agent_contact?: string|null
     * }  $evidence
     */
    public function completeHandover(Order $order, Admin $admin, array $evidence = []): CustomerAgentPickup
    {
        return DB::transaction(function () use ($order, $admin, $evidence): CustomerAgentPickup {
            $pickup = $this->lockPickup($order);
            $key = 'agent-handover-complete:'.$order->id;

            if ($pickup->pickup_status === AgentPickupStatus::HandoverCompleted
                && $pickup->handover_completed_at !== null
            ) {
                return $pickup;
            }

            $this->assertAuthorizationValid($pickup);
            $this->assertPickupPrerequisites($order);

            if ($pickup->release_status !== WarehouseReleaseStatus::Released) {
                // Auto-complete release path if warehouse already picked up goods at dock.
                if ($pickup->release_status === WarehouseReleaseStatus::PickedUp
                    || $pickup->release_status === WarehouseReleaseStatus::ReadyForPickup
                    || $pickup->release_status === WarehouseReleaseStatus::PickupScheduled
                    || $pickup->release_status === WarehouseReleaseStatus::Reattempt
                ) {
                    $this->transitionRelease($pickup, WarehouseReleaseStatus::PickedUp, $admin, 'Auto picked up at handover');
                    $this->transitionRelease($pickup, WarehouseReleaseStatus::Released, $admin, 'Auto released at handover');
                    $pickup->forceFill([
                        'released_at' => now(),
                        'release_operator_id' => $admin->id,
                        'picked_up_at' => $pickup->picked_up_at ?? now(),
                    ])->save();
                    $this->advanceFulfillmentTowardReleased($order);
                } else {
                    throw ValidationException::withMessages([
                        'release' => ['Warehouse must release goods before handover can complete.'],
                    ]);
                }
            }

            if ($pickup->identity_verified_at === null) {
                $pickup->forceFill(['identity_verified_at' => now()])->save();
            }
            if ($pickup->authorization_verified_at === null) {
                $pickup->forceFill(['authorization_verified_at' => now()])->save();
            }
            if ($pickup->goods_verified_at === null) {
                $pickup->forceFill(['goods_verified_at' => now()])->save();
            }

            $evidencePayload = [
                'signature' => $evidence['signature'] ?? null,
                'reference_number' => $evidence['reference_number'] ?? $pickup->pickup_reference,
                'document_number' => $evidence['document_number'] ?? null,
                'photos' => $evidence['photos'] ?? [],
                'notes' => $evidence['notes'] ?? null,
                'metadata' => $evidence['metadata'] ?? [],
                'captured_at' => now()->toIso8601String(),
            ];

            $from = $pickup->pickup_status->value;
            $pickup->forceFill([
                'pickup_status' => AgentPickupStatus::HandoverCompleted,
                'handover_completed_at' => now(),
                'handover_operator_id' => $admin->id,
                'evidence' => $evidencePayload,
                'pickup_notes' => $evidence['notes'] ?? $pickup->pickup_notes,
            ])->save();

            $this->writeHistory(
                $pickup,
                $admin,
                'handover_completed',
                $from,
                AgentPickupStatus::HandoverCompleted->value,
                $evidence['notes'] ?? 'Agent handover completed — tracking transfers to customer agent',
                [
                    'evidence' => $evidencePayload,
                    'tracking_boundary' => 'company_responsibility_ended',
                ],
                $key,
            );

            // Sync China workflow stage (consumes Export Ready already validated).
            $agentName = $evidence['agent_name'] ?? $pickup->agent_name;
            $agentContact = $evidence['agent_contact'] ?? $pickup->agent_contact ?? $pickup->agent_phone;
            $this->chinaWorkflow->recordAgentHandoff(
                $order,
                $admin,
                $agentName,
                $agentContact,
                is_string($evidence['notes'] ?? null) ? $evidence['notes'] : 'Customer agent handover completed',
            );

            $this->advanceFulfillmentToDelivered($order);

            event(new AgentHandoverCompleted($pickup->id, $pickup->order_id, $admin->id));
            event(new PickupCompleted($pickup->id, $pickup->order_id, $admin->id));
            $this->safeNotifyCustomer($pickup, NotificationEventType::AgentHandoverCompleted, 'Handed to your shipping agent');

            return $pickup->fresh(['histories']) ?? $pickup;
        });
    }

    /**
     * Customer-facing tracking payload (pickup confirmation only — no transport tracking).
     *
     * @return array<string, mixed>
     */
    public function trackingPayload(Order $order): array
    {
        $pickup = $this->showForOrder($order);
        if ($pickup === null) {
            return [
                'source' => 'customer_agent_pickup',
                'tracking_ownership' => 'customer_agent',
                'current_status' => AgentPickupStatus::AwaitingPickup->value,
                'current_status_label' => AgentPickupStatus::AwaitingPickup->label(),
                'timeline' => [],
                'pickup' => null,
            ];
        }

        $pickup->refreshAuthorizationExpiry();

        $steps = [
            AgentPickupStatus::AwaitingPickup,
            AgentPickupStatus::PickupScheduled,
            AgentPickupStatus::AgentArrived,
            AgentPickupStatus::HandoverCompleted,
        ];

        $current = $pickup->pickup_status;
        $timeline = [];
        foreach ($steps as $step) {
            $reached = match ($step) {
                AgentPickupStatus::AwaitingPickup => true,
                AgentPickupStatus::PickupScheduled => $pickup->pickup_scheduled_at !== null
                    || in_array($current, [AgentPickupStatus::AgentArrived, AgentPickupStatus::HandoverCompleted, AgentPickupStatus::IdentityVerified, AgentPickupStatus::AuthorizationVerified, AgentPickupStatus::GoodsVerified], true),
                AgentPickupStatus::AgentArrived => $pickup->agent_arrived_at !== null
                    || in_array($current, [AgentPickupStatus::HandoverCompleted, AgentPickupStatus::IdentityVerified, AgentPickupStatus::AuthorizationVerified, AgentPickupStatus::GoodsVerified], true),
                AgentPickupStatus::HandoverCompleted => $pickup->handover_completed_at !== null,
                default => false,
            };

            $timeline[] = [
                'status' => $step->value,
                'label' => $step->label(),
                'reached' => $reached,
                'at' => match ($step) {
                    AgentPickupStatus::PickupScheduled => $pickup->pickup_scheduled_at?->toIso8601String(),
                    AgentPickupStatus::AgentArrived => $pickup->agent_arrived_at?->toIso8601String(),
                    AgentPickupStatus::HandoverCompleted => $pickup->handover_completed_at?->toIso8601String(),
                    default => null,
                },
            ];
        }

        return [
            'source' => 'customer_agent_pickup',
            'tracking_ownership' => 'customer_agent',
            'company_transport_tracking' => false,
            'current_status' => $current->value,
            'current_status_label' => $current->label(),
            'authorization_status' => $pickup->authorization_status->value,
            'release_status' => $pickup->release_status?->value,
            'timeline' => $timeline,
            'pickup' => [
                'pickup_reference' => $pickup->pickup_reference,
                'agent_name' => $pickup->agent_name,
                'agent_company' => $pickup->agent_company,
                'agent_phone' => $pickup->agent_phone,
                'agent_email' => $pickup->agent_email,
                'handover_completed_at' => $pickup->handover_completed_at?->toIso8601String(),
            ],
        ];
    }

    private function verifyStep(
        Order $order,
        Admin $admin,
        string $timestampField,
        AgentPickupStatus $status,
        string $action,
        ?string $notes,
    ): CustomerAgentPickup {
        return DB::transaction(function () use ($order, $admin, $timestampField, $status, $action, $notes): CustomerAgentPickup {
            $pickup = $this->lockPickup($order);
            $this->assertAuthorizationValid($pickup);
            $key = 'agent-'.$action.':'.$order->id;

            if ($pickup->{$timestampField} !== null && $this->historyExists($key)) {
                return $pickup;
            }

            $from = $pickup->pickup_status->value;
            $pickup->forceFill([
                $timestampField => now(),
                'pickup_status' => $status,
            ])->save();

            $this->writeHistory($pickup, $admin, $action, $from, $status->value, $notes, [], $key);

            return $pickup->fresh(['histories']) ?? $pickup;
        });
    }

    private function transitionRelease(
        CustomerAgentPickup $pickup,
        WarehouseReleaseStatus $to,
        Admin $admin,
        ?string $notes = null,
    ): void {
        $from = $pickup->release_status;

        if ($from === null) {
            if (! in_array($to, [WarehouseReleaseStatus::ReadyForPickup, WarehouseReleaseStatus::PickupScheduled, WarehouseReleaseStatus::PickedUp], true)) {
                throw ValidationException::withMessages([
                    'release' => ['Warehouse release must start at ready_for_pickup.'],
                ]);
            }
        } elseif ($from === $to) {
            return;
        } elseif (! $from->canTransitionTo($to)) {
            // Allow ReadyForPickup → PickedUp shortcut via PickedUp from Ready
            if ($from === WarehouseReleaseStatus::ReadyForPickup && $to === WarehouseReleaseStatus::Released) {
                $pickup->forceFill(['release_status' => WarehouseReleaseStatus::PickedUp])->save();
                $from = WarehouseReleaseStatus::PickedUp;
            } elseif ($from === WarehouseReleaseStatus::PickupScheduled && $to === WarehouseReleaseStatus::Released) {
                $pickup->forceFill(['release_status' => WarehouseReleaseStatus::PickedUp])->save();
                $from = WarehouseReleaseStatus::PickedUp;
            } elseif ($from === WarehouseReleaseStatus::Reattempt && $to === WarehouseReleaseStatus::Released) {
                $pickup->forceFill(['release_status' => WarehouseReleaseStatus::PickedUp])->save();
                $from = WarehouseReleaseStatus::PickedUp;
            }

            if ($from !== null && $from !== $to && ! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'release' => ["Cannot transition warehouse release from [{$from->value}] to [{$to->value}]."],
                ]);
            }
        }

        $pickup->forceFill([
            'release_status' => $to,
            'release_notes' => $notes ?? $pickup->release_notes,
            'release_operator_id' => $admin->id,
        ])->save();
    }

    private function assertPickupPrerequisites(Order $order, bool $requireAuthorization = true): void
    {
        $order->loadMissing(['fulfillment.warehouseJob', 'deliveryOption']);
        $fulfillment = $order->fulfillment;

        if ($fulfillment === null) {
            throw ValidationException::withMessages([
                'fulfillment' => ['Fulfillment is required for Customer Agent pickup.'],
            ]);
        }

        $evaluation = $this->eligibility->evaluateCustomerAgentPickup($fulfillment, $requireAuthorization);

        if (! $evaluation['eligible']) {
            throw ValidationException::withMessages([
                'pickup' => [$evaluation['reason'] ?? 'Customer Agent pickup prerequisites not met.'],
            ]);
        }
    }

    private function assertAuthorizationValid(CustomerAgentPickup $pickup): void
    {
        $pickup->refreshAuthorizationExpiry();
        $pickup->refresh();

        if (! $pickup->hasValidAuthorization()) {
            throw ValidationException::withMessages([
                'authorization' => [
                    'Valid pickup authorization is required. Current status: '.$pickup->authorization_status->value.'.',
                ],
            ]);
        }
    }

    private function advanceFulfillmentTowardReleased(Order $order): void
    {
        $fulfillment = $order->fresh()?->fulfillment;
        if ($fulfillment === null) {
            return;
        }

        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) $fulfillment->status);

        if ($status === FulfillmentStatus::ReadyForShipping) {
            $this->fulfillment->updateStatus($fulfillment, [
                'status' => FulfillmentStatus::Shipped->value,
                'notes' => 'Released to customer shipping agent',
            ]);
        }
    }

    private function advanceFulfillmentToDelivered(Order $order): void
    {
        $fulfillment = $order->fresh()?->fulfillment;
        if ($fulfillment === null) {
            return;
        }

        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) $fulfillment->status);

        if ($status === FulfillmentStatus::ReadyForShipping) {
            $this->fulfillment->updateStatus($fulfillment, [
                'status' => FulfillmentStatus::Shipped->value,
                'notes' => 'Released to customer shipping agent',
            ]);
            $fulfillment = $fulfillment->fresh() ?? $fulfillment;
            $status = FulfillmentStatus::Shipped;
        }

        if ($status === FulfillmentStatus::Shipped) {
            $this->fulfillment->updateStatus($fulfillment, [
                'status' => FulfillmentStatus::Delivered->value,
                'notes' => 'Customer agent handover completed',
            ]);
        }
    }

    private function lockOrBootstrap(Order $order, ?Admin $admin): CustomerAgentPickup
    {
        $pickup = CustomerAgentPickup::query()->where('order_id', $order->id)->lockForUpdate()->first();
        if ($pickup !== null) {
            return $pickup;
        }

        $this->bootstrap($order, $admin);

        return CustomerAgentPickup::query()->where('order_id', $order->id)->lockForUpdate()->firstOrFail();
    }

    private function lockPickup(Order $order): CustomerAgentPickup
    {
        $pickup = CustomerAgentPickup::query()->where('order_id', $order->id)->lockForUpdate()->first();
        if ($pickup === null) {
            throw ValidationException::withMessages([
                'pickup' => ['Customer Agent pickup has not been bootstrapped for this order.'],
            ]);
        }

        return $pickup;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function writeHistory(
        CustomerAgentPickup $pickup,
        ?Admin $admin,
        string $action,
        ?string $from,
        ?string $to,
        ?string $reason,
        array $metadata,
        ?string $idempotencyKey,
    ): void {
        if ($idempotencyKey !== null && $this->historyExists($idempotencyKey)) {
            return;
        }

        CustomerAgentPickupHistory::query()->create([
            'customer_agent_pickup_id' => $pickup->id,
            'order_id' => $pickup->order_id,
            'admin_id' => $admin?->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'metadata' => $metadata !== [] ? $metadata : null,
            'idempotency_key' => $idempotencyKey,
            'created_at' => now(),
        ]);
    }

    private function historyExists(string $key): bool
    {
        return CustomerAgentPickupHistory::query()->where('idempotency_key', $key)->exists();
    }

    private function safeNotifyCustomer(CustomerAgentPickup $pickup, NotificationEventType $type, string $title): void
    {
        try {
            $pickup->loadMissing('order.user');
            $user = $pickup->order?->user;
            if ($user === null) {
                return;
            }

            $this->notifications->notifyCustomer($type, $user, [
                'order_id' => $pickup->order_id,
                'pickup_reference' => $pickup->pickup_reference,
                'agent_name' => $pickup->agent_name,
                'message' => $title,
            ], title: $title);
        } catch (\Throwable $e) {
            Log::warning('customer_agent.notification_failed', [
                'type' => $type->value,
                'pickup_id' => $pickup->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
