<?php

namespace App\Services\Crm;

use App\Enums\CustomerTimelineEventType;
use App\Models\CustomerProfile;
use App\Models\CustomerTimelineEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Append-only customer-centric timeline. No update/delete in normal services.
 */
class CustomerTimelineService
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function append(
        CustomerProfile $profile,
        CustomerTimelineEventType $type,
        string $title,
        ?string $description = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?array $metadata = null,
        mixed $occurredAt = null,
    ): CustomerTimelineEvent {
        return CustomerTimelineEvent::query()->create([
            'customer_profile_id' => $profile->id,
            'event_type' => $type,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'title' => $title,
            'description' => $description,
            'metadata' => $metadata,
            'occurred_at' => $occurredAt ?? now(),
            'created_at' => now(),
        ]);
    }

    public function paginate(CustomerProfile $profile, int $perPage = 20): LengthAwarePaginator
    {
        return CustomerTimelineEvent::query()
            ->where('customer_profile_id', $profile->id)
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
