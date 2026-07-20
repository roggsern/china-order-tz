<?php

namespace App\Services\Crm;

use App\Enums\CustomerLifecycleStatus;
use App\Enums\CustomerTimelineEventType;
use App\Events\Crm\CustomerBlocked;
use App\Events\Crm\CustomerStatusChanged;
use App\Events\Crm\CustomerUnblocked;
use App\Models\Admin;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CustomerStatusService
{
    public function __construct(
        private readonly CustomerTimelineService $timeline,
    ) {}

    /**
     * @param  array{lifecycle_status: string, block_reason?: string|null}  $data
     */
    public function updateStatus(CustomerProfile $profile, array $data, ?Admin $admin = null): CustomerProfile
    {
        $status = CustomerLifecycleStatus::from($data['lifecycle_status']);

        return match ($status) {
            CustomerLifecycleStatus::Blocked => $this->block(
                $profile,
                (string) ($data['block_reason'] ?? ''),
                $admin,
            ),
            CustomerLifecycleStatus::Active => $this->activate($profile, $admin),
            CustomerLifecycleStatus::Dormant => $this->markDormant($profile, $admin),
        };
    }

    public function block(CustomerProfile $profile, string $reason, ?Admin $admin = null): CustomerProfile
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'block_reason' => ['A block reason is required.'],
            ]);
        }

        return DB::transaction(function () use ($profile, $reason, $admin) {
            /** @var CustomerProfile $locked */
            $locked = CustomerProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $before = $locked->lifecycle_status;

            $locked->update([
                'lifecycle_status' => CustomerLifecycleStatus::Blocked,
                'blocked_at' => now(),
                'blocked_by' => $admin?->id,
                'block_reason' => $reason,
            ]);

            User::query()->whereKey($locked->user_id)->update(['is_active' => false]);
            // Revoke customer API tokens so blocked accounts cannot continue sessions.
            $locked->user?->tokens()->delete();

            $fresh = $locked->fresh(['user', 'metrics', 'tags']) ?? $locked;

            $this->timeline->append(
                $fresh,
                CustomerTimelineEventType::CustomerBlocked,
                'Customer blocked',
                $reason,
                CustomerProfile::class,
                $fresh->id,
                ['previous_status' => $before?->value],
            );

            $this->dispatchStatusEvents($fresh, $before, CustomerLifecycleStatus::Blocked, $admin, $reason);

            try {
                event(new CustomerBlocked($fresh, $reason, $admin));
            } catch (\Throwable $e) {
                Log::warning('crm.customer_blocked_event_failed', ['message' => $e->getMessage()]);
            }

            return $fresh;
        });
    }

    public function unblock(CustomerProfile $profile, ?Admin $admin = null): CustomerProfile
    {
        return $this->activate($profile, $admin);
    }

    public function activate(CustomerProfile $profile, ?Admin $admin = null): CustomerProfile
    {
        return DB::transaction(function () use ($profile, $admin) {
            /** @var CustomerProfile $locked */
            $locked = CustomerProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $before = $locked->lifecycle_status;
            $wasBlocked = $before === CustomerLifecycleStatus::Blocked;

            $locked->update([
                'lifecycle_status' => CustomerLifecycleStatus::Active,
                'blocked_at' => null,
                'blocked_by' => null,
                'block_reason' => null,
            ]);

            User::query()->whereKey($locked->user_id)->update(['is_active' => true]);

            $fresh = $locked->fresh(['user', 'metrics', 'tags']) ?? $locked;

            if ($wasBlocked) {
                $this->timeline->append(
                    $fresh,
                    CustomerTimelineEventType::CustomerUnblocked,
                    'Customer unblocked',
                    null,
                    CustomerProfile::class,
                    $fresh->id,
                    ['previous_status' => $before?->value],
                );
                try {
                    event(new CustomerUnblocked($fresh, $admin));
                } catch (\Throwable $e) {
                    Log::warning('crm.customer_unblocked_event_failed', ['message' => $e->getMessage()]);
                }
            } else {
                $this->timeline->append(
                    $fresh,
                    CustomerTimelineEventType::StatusChanged,
                    'Lifecycle status changed to active',
                    null,
                    CustomerProfile::class,
                    $fresh->id,
                    [
                        'from' => $before?->value,
                        'to' => CustomerLifecycleStatus::Active->value,
                    ],
                );
            }

            $this->dispatchStatusEvents($fresh, $before, CustomerLifecycleStatus::Active, $admin);

            return $fresh;
        });
    }

    public function markDormant(CustomerProfile $profile, ?Admin $admin = null): CustomerProfile
    {
        return DB::transaction(function () use ($profile, $admin) {
            /** @var CustomerProfile $locked */
            $locked = CustomerProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            if ($locked->lifecycle_status === CustomerLifecycleStatus::Blocked) {
                throw ValidationException::withMessages([
                    'lifecycle_status' => ['Unblock the customer before marking dormant.'],
                ]);
            }

            $before = $locked->lifecycle_status;
            $locked->update([
                'lifecycle_status' => CustomerLifecycleStatus::Dormant,
            ]);

            $fresh = $locked->fresh(['user', 'metrics', 'tags']) ?? $locked;

            $this->timeline->append(
                $fresh,
                CustomerTimelineEventType::StatusChanged,
                'Lifecycle status changed to dormant',
                null,
                CustomerProfile::class,
                $fresh->id,
                [
                    'from' => $before?->value,
                    'to' => CustomerLifecycleStatus::Dormant->value,
                ],
            );

            $this->dispatchStatusEvents($fresh, $before, CustomerLifecycleStatus::Dormant, $admin);

            return $fresh;
        });
    }

    private function dispatchStatusEvents(
        CustomerProfile $profile,
        ?CustomerLifecycleStatus $from,
        CustomerLifecycleStatus $to,
        ?Admin $admin,
        ?string $reason = null,
    ): void {
        if ($from === $to) {
            return;
        }

        try {
            event(new CustomerStatusChanged($profile, $from, $to, $admin, $reason));
        } catch (\Throwable $e) {
            Log::warning('crm.customer_status_changed_event_failed', ['message' => $e->getMessage()]);
        }
    }
}
