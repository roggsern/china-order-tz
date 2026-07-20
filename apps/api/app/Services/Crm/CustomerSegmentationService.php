<?php

namespace App\Services\Crm;

use App\Enums\CustomerTimelineEventType;
use App\Events\Crm\CustomerTagAssigned;
use App\Events\Crm\CustomerTagRemoved;
use App\Models\Admin;
use App\Models\CustomerProfile;
use App\Models\CustomerTag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Manual tags + computed CRM segment helpers. Tags are data-driven (seeded), not hard-coded VIP roles.
 */
class CustomerSegmentationService
{
    public function __construct(
        private readonly CustomerTimelineService $timeline,
    ) {}

    /**
     * @return list<CustomerTag>
     */
    public function listTags(bool $activeOnly = false): array
    {
        $query = CustomerTag::query()->orderBy('name');
        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get()->all();
    }

    /**
     * @param  array{name: string, slug?: string|null, description?: string|null, is_active?: bool}  $data
     */
    public function createTag(array $data): CustomerTag
    {
        return CustomerTag::query()->create([
            'name' => trim($data['name']),
            'slug' => filled($data['slug'] ?? null) ? Str::slug((string) $data['slug']) : Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * @param  array{name?: string, slug?: string|null, description?: string|null, is_active?: bool}  $data
     */
    public function updateTag(CustomerTag $tag, array $data): CustomerTag
    {
        if (array_key_exists('name', $data) && filled($data['name'])) {
            $tag->name = trim((string) $data['name']);
        }
        if (array_key_exists('slug', $data) && filled($data['slug'])) {
            $tag->slug = Str::slug((string) $data['slug']);
        }
        if (array_key_exists('description', $data)) {
            $tag->description = $data['description'];
        }
        if (array_key_exists('is_active', $data)) {
            $tag->is_active = (bool) $data['is_active'];
        }
        $tag->save();

        return $tag->fresh() ?? $tag;
    }

    public function assignTag(CustomerProfile $profile, CustomerTag $tag, ?Admin $admin = null): CustomerProfile
    {
        if (! $tag->is_active) {
            throw ValidationException::withMessages([
                'tag' => ['Cannot assign an inactive tag.'],
            ]);
        }

        return DB::transaction(function () use ($profile, $tag, $admin) {
            $already = $profile->tags()->where('customer_tags.id', $tag->id)->exists();
            if (! $already) {
                $profile->tags()->attach($tag->id, [
                    'assigned_by' => $admin?->id,
                    'assigned_at' => now(),
                ]);

                $this->timeline->append(
                    $profile,
                    CustomerTimelineEventType::TagAssigned,
                    'Tag assigned: '.$tag->name,
                    null,
                    CustomerTag::class,
                    $tag->id,
                    ['tag_slug' => $tag->slug],
                );

                try {
                    event(new CustomerTagAssigned($profile, $tag, $admin));
                } catch (\Throwable $e) {
                    Log::warning('crm.tag_assigned_event_failed', ['message' => $e->getMessage()]);
                }
            }

            return $profile->fresh(['user', 'metrics', 'tags']) ?? $profile;
        });
    }

    public function removeTag(CustomerProfile $profile, CustomerTag $tag, ?Admin $admin = null): CustomerProfile
    {
        return DB::transaction(function () use ($profile, $tag, $admin) {
            $detached = $profile->tags()->detach($tag->id);
            if ($detached > 0) {
                $this->timeline->append(
                    $profile,
                    CustomerTimelineEventType::TagRemoved,
                    'Tag removed: '.$tag->name,
                    null,
                    CustomerTag::class,
                    $tag->id,
                    ['tag_slug' => $tag->slug],
                );

                try {
                    event(new CustomerTagRemoved($profile, $tag, $admin));
                } catch (\Throwable $e) {
                    Log::warning('crm.tag_removed_event_failed', ['message' => $e->getMessage()]);
                }
            }

            return $profile->fresh(['user', 'metrics', 'tags']) ?? $profile;
        });
    }

    /** Computed segment: registered within configured new-customer window. */
    public function isNew(CustomerProfile $profile): bool
    {
        $days = (int) config('crm.new_customer_days', 30);

        return $profile->created_at !== null
            && $profile->created_at->gte(now()->subDays($days));
    }
}
