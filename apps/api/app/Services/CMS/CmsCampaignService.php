<?php

namespace App\Services\CMS;

use App\DTOs\CMS\CreateCmsCampaignData;
use App\DTOs\CMS\UpdateCmsCampaignData;
use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsStatus;
use App\Events\Audit\CmsPlatformAudit;
use App\Models\Admin;
use App\Models\CmsCampaign;
use App\Models\CmsFeaturedContent;
use App\Models\CmsHeroSlide;
use App\Models\CmsHomepageLayout;
use App\Models\CmsNavigationShell;
use App\Models\Promotion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CMS Campaign Experience Engine — storefront orchestration.
 * Does not replace GrowthCampaign (engagement/messaging).
 */
class CmsCampaignService
{
    /**
     * @param  array{status?: string, commerce_context?: string, search?: string}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = CmsCampaign::query()->with(['layout'])->withCount(['heroSlides', 'featuredContents', 'promotions', 'navigationShells'])->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['commerce_context'])) {
            $query->where('commerce_context', $filters['commerce_context']);
        }
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)->orWhere('slug', 'like', $search);
            });
        }

        return $query->paginate($perPage);
    }

    public function show(CmsCampaign $campaign): CmsCampaign
    {
        return $campaign->load(['layout', 'heroSlides', 'featuredContents', 'promotions', 'navigationShells', 'creator']);
    }

    public function create(CreateCmsCampaignData $data, ?Admin $admin = null): CmsCampaign
    {
        return DB::transaction(function () use ($data, $admin) {
            $this->assertSchedule($data->startsAt, $data->endsAt);
            $this->assertLayoutCompatible($data->homepageLayoutId, $data->commerceContext);

            if ($data->status === CmsStatus::Archived && $data->isDefault) {
                throw ValidationException::withMessages([
                    'is_default' => ['An archived campaign cannot be marked as default.'],
                ]);
            }

            if ($data->isDefault) {
                $this->clearDefaultForContext($data->commerceContext);
            }

            $campaign = CmsCampaign::query()->create([
                'name' => $data->name,
                'slug' => $data->slug,
                'description' => $data->description,
                'commerce_context' => $data->commerceContext,
                'status' => $data->status,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'priority' => max(0, $data->priority),
                'is_default' => $data->isDefault,
                'default_slot' => $data->isDefault ? $data->commerceContext->value : null,
                'cms_homepage_layout_id' => $data->homepageLayoutId,
                'created_by' => $admin?->id,
            ]);

            event(CmsPlatformAudit::campaignCreated($campaign, $admin));

            return $campaign->fresh(['layout']) ?? $campaign;
        });
    }

    public function update(CmsCampaign $campaign, UpdateCmsCampaignData $data, ?Admin $admin = null): CmsCampaign
    {
        return DB::transaction(function () use ($campaign, $data, $admin) {
            $campaign = CmsCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $oldPriority = $campaign->priority;
            $oldStarts = $campaign->starts_at?->toIso8601String();
            $oldEnds = $campaign->ends_at?->toIso8601String();
            $oldStatus = $campaign->status;

            if ($data->has('name') && $data->name !== null) {
                $campaign->name = $data->name;
            }
            if ($data->has('slug') && $data->slug !== null) {
                $campaign->slug = $data->slug;
            }
            if ($data->has('description')) {
                $campaign->description = $data->description;
            }

            $context = $campaign->commerce_context;
            if ($data->has('commerce_context') && $data->commerceContext !== null) {
                $context = $data->commerceContext;
                $campaign->commerce_context = $context;
            }

            $starts = $data->has('starts_at') ? $data->startsAt : $campaign->starts_at;
            $ends = $data->has('ends_at') ? $data->endsAt : $campaign->ends_at;
            $this->assertSchedule($starts, $ends);
            if ($data->has('starts_at')) {
                $campaign->starts_at = $data->startsAt;
            }
            if ($data->has('ends_at')) {
                $campaign->ends_at = $data->endsAt;
            }

            if ($data->has('priority') && $data->priority !== null) {
                $campaign->priority = max(0, $data->priority);
            }

            $layoutId = $data->has('cms_homepage_layout_id')
                ? $data->homepageLayoutId
                : $campaign->cms_homepage_layout_id;
            $this->assertLayoutCompatible($layoutId, $context);
            if ($data->has('cms_homepage_layout_id')) {
                $campaign->cms_homepage_layout_id = $data->homepageLayoutId;
            }

            $status = $data->has('status') && $data->status !== null ? $data->status : $campaign->status;
            $wantsDefault = $data->has('is_default') ? (bool) $data->isDefault : $campaign->is_default;

            if ($status === CmsStatus::Archived && ($wantsDefault || $campaign->is_default)) {
                throw ValidationException::withMessages([
                    'status' => ['A default campaign cannot be archived. Unset default first.'],
                ]);
            }
            $campaign->status = $status;

            if ($wantsDefault) {
                $this->clearDefaultForContext($context, exceptId: $campaign->id);
                $campaign->is_default = true;
                $campaign->default_slot = $context->value;
            } elseif ($data->has('is_default') && ! $wantsDefault) {
                $campaign->is_default = false;
                $campaign->default_slot = null;
            } elseif ($campaign->is_default && $data->has('commerce_context')) {
                $this->clearDefaultForContext($context, exceptId: $campaign->id);
                $campaign->default_slot = $context->value;
            }

            $campaign->save();

            if ($data->has('status') && $status === CmsStatus::Active && $oldStatus !== CmsStatus::Active) {
                event(CmsPlatformAudit::campaignActivated($campaign, $admin));
            } elseif ($data->has('status') && $status === CmsStatus::Archived && $oldStatus !== CmsStatus::Archived) {
                event(CmsPlatformAudit::campaignArchived($campaign, $admin));
            } elseif ($data->has('priority') && $oldPriority !== $campaign->priority) {
                event(CmsPlatformAudit::campaignPriorityChanged($campaign, $admin, $oldPriority));
            } elseif ($data->has('starts_at') || $data->has('ends_at')) {
                event(CmsPlatformAudit::campaignScheduleChanged($campaign, $admin, $oldStarts, $oldEnds));
            } else {
                event(CmsPlatformAudit::campaignUpdated($campaign, $admin));
            }

            return $campaign->fresh(['layout', 'heroSlides', 'featuredContents', 'promotions']) ?? $campaign;
        });
    }

    public function activate(CmsCampaign $campaign, ?Admin $admin = null): CmsCampaign
    {
        return $this->update($campaign, UpdateCmsCampaignData::fromArray([
            'status' => CmsStatus::Active->value,
        ]), $admin);
    }

    public function archive(CmsCampaign $campaign, ?Admin $admin = null): CmsCampaign
    {
        return DB::transaction(function () use ($campaign, $admin) {
            $campaign = CmsCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            if ($campaign->is_default) {
                throw ValidationException::withMessages([
                    'campaign' => ['A default campaign cannot be archived. Unset default first.'],
                ]);
            }
            $campaign->forceFill([
                'status' => CmsStatus::Archived,
                'is_default' => false,
                'default_slot' => null,
            ])->save();
            event(CmsPlatformAudit::campaignArchived($campaign, $admin));

            return $campaign->fresh(['layout']) ?? $campaign;
        });
    }

    public function updatePriority(CmsCampaign $campaign, int $priority, ?Admin $admin = null): CmsCampaign
    {
        return $this->update($campaign, UpdateCmsCampaignData::fromArray([
            'priority' => max(0, $priority),
        ]), $admin);
    }

    public function attachLayout(CmsCampaign $campaign, string $layoutId, ?Admin $admin = null): CmsCampaign
    {
        return $this->update($campaign, UpdateCmsCampaignData::fromArray([
            'cms_homepage_layout_id' => $layoutId,
        ]), $admin);
    }

    /**
     * @param  list<string>  $slideIds
     */
    public function attachHeroSlides(CmsCampaign $campaign, array $slideIds, ?Admin $admin = null): CmsCampaign
    {
        return DB::transaction(function () use ($campaign, $slideIds, $admin) {
            $campaign = CmsCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $this->assertHeroSlidesBelongToCampaignLayout($campaign, $slideIds);

            $sync = [];
            foreach (array_values(array_unique($slideIds)) as $index => $id) {
                $sync[$id] = ['position' => $index];
            }
            $campaign->heroSlides()->sync($sync);
            event(CmsPlatformAudit::campaignUpdated($campaign, $admin));

            return $campaign->fresh(['layout', 'heroSlides']) ?? $campaign;
        });
    }

    /**
     * @param  list<string>  $featuredIds
     */
    public function attachFeaturedContents(CmsCampaign $campaign, array $featuredIds, ?Admin $admin = null): CmsCampaign
    {
        return DB::transaction(function () use ($campaign, $featuredIds, $admin) {
            $campaign = CmsCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $this->assertFeaturedBelongToCampaignLayout($campaign, $featuredIds);

            $sync = [];
            foreach (array_values(array_unique($featuredIds)) as $index => $id) {
                $sync[$id] = ['position' => $index];
            }
            $campaign->featuredContents()->sync($sync);
            event(CmsPlatformAudit::campaignUpdated($campaign, $admin));

            return $campaign->fresh(['layout', 'featuredContents']) ?? $campaign;
        });
    }

    /**
     * @param  list<string>  $promotionIds
     */
    public function attachPromotions(CmsCampaign $campaign, array $promotionIds, ?Admin $admin = null): CmsCampaign
    {
        return DB::transaction(function () use ($campaign, $promotionIds, $admin) {
            $campaign = CmsCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $ids = array_values(array_unique($promotionIds));
            foreach ($ids as $id) {
                if (Promotion::query()->whereKey($id)->doesntExist()) {
                    throw ValidationException::withMessages([
                        'promotion_ids' => ["Promotion {$id} does not exist."],
                    ]);
                }
            }
            $sync = [];
            foreach ($ids as $index => $id) {
                $sync[$id] = ['position' => $index];
            }
            $campaign->promotions()->sync($sync);
            event(CmsPlatformAudit::campaignUpdated($campaign, $admin));

            return $campaign->fresh(['promotions']) ?? $campaign;
        });
    }

    /**
     * Attach navigation shells (reference only). One shell per navigation_type.
     *
     * @param  list<string>  $shellIds
     */
    public function attachNavigationShells(CmsCampaign $campaign, array $shellIds, ?Admin $admin = null): CmsCampaign
    {
        return DB::transaction(function () use ($campaign, $shellIds, $admin) {
            $campaign = CmsCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $ids = array_values(array_unique($shellIds));
            $seenTypes = [];

            foreach ($ids as $id) {
                $shell = CmsNavigationShell::query()->find($id);
                if ($shell === null) {
                    throw ValidationException::withMessages([
                        'navigation_shell_ids' => ["Navigation shell {$id} does not exist."],
                    ]);
                }
                if ($shell->commerce_context !== $campaign->commerce_context) {
                    throw ValidationException::withMessages([
                        'navigation_shell_ids' => [
                            sprintf(
                                'Navigation shell context (%s) must match campaign context (%s).',
                                $shell->commerce_context->value,
                                $campaign->commerce_context->value,
                            ),
                        ],
                    ]);
                }
                if ($shell->status === CmsStatus::Archived) {
                    throw ValidationException::withMessages([
                        'navigation_shell_ids' => ['Cannot attach an archived navigation shell.'],
                    ]);
                }
                $typeKey = $shell->navigation_type->value;
                if (isset($seenTypes[$typeKey])) {
                    throw ValidationException::withMessages([
                        'navigation_shell_ids' => [
                            "Only one navigation shell of type {$typeKey} may be attached to a campaign.",
                        ],
                    ]);
                }
                $seenTypes[$typeKey] = true;
            }

            $campaign->navigationShells()->sync($ids);
            event(CmsPlatformAudit::campaignUpdated($campaign, $admin));

            return $campaign->fresh(['navigationShells']) ?? $campaign;
        });
    }

    /**
     * Highest-priority active scheduled campaign for a commerce context.
     */
    public function findActiveCampaign(CmsCommerceContext $context): ?CmsCampaign
    {
        return CmsCampaign::query()
            ->storefrontEligible()
            ->where('commerce_context', $context->value)
            ->whereNotNull('cms_homepage_layout_id')
            ->orderByDesc('priority')
            ->orderByDesc('starts_at')
            ->orderByDesc('created_at')
            ->first();
    }

    private function assertSchedule(mixed $startsAt, mixed $endsAt): void
    {
        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            throw ValidationException::withMessages([
                'ends_at' => ['ends_at must be later than starts_at.'],
            ]);
        }
    }

    private function assertLayoutCompatible(?string $layoutId, CmsCommerceContext $context): void
    {
        if ($layoutId === null) {
            return;
        }

        $layout = CmsHomepageLayout::query()->find($layoutId);
        if ($layout === null) {
            throw ValidationException::withMessages([
                'cms_homepage_layout_id' => ['Referenced homepage layout does not exist.'],
            ]);
        }

        if ($layout->commerce_context !== $context) {
            throw ValidationException::withMessages([
                'cms_homepage_layout_id' => [
                    sprintf(
                        'Homepage layout commerce context (%s) must match campaign context (%s).',
                        $layout->commerce_context->value,
                        $context->value,
                    ),
                ],
            ]);
        }

        if ($layout->status === CmsStatus::Archived) {
            throw ValidationException::withMessages([
                'cms_homepage_layout_id' => ['Cannot attach an archived homepage layout.'],
            ]);
        }
    }

    /**
     * @param  list<string>  $slideIds
     */
    private function assertHeroSlidesBelongToCampaignLayout(CmsCampaign $campaign, array $slideIds): void
    {
        if ($campaign->cms_homepage_layout_id === null) {
            throw ValidationException::withMessages([
                'cms_homepage_layout_id' => ['Attach a homepage layout before attaching hero slides.'],
            ]);
        }

        foreach (array_unique($slideIds) as $id) {
            $slide = CmsHeroSlide::query()->with('section')->find($id);
            if ($slide === null) {
                throw ValidationException::withMessages([
                    'hero_slide_ids' => ["Hero slide {$id} does not exist."],
                ]);
            }
            if ($slide->section?->cms_homepage_layout_id !== $campaign->cms_homepage_layout_id) {
                throw ValidationException::withMessages([
                    'hero_slide_ids' => [
                        "Hero slide {$id} does not belong to the campaign homepage layout.",
                    ],
                ]);
            }
        }
    }

    /**
     * @param  list<string>  $featuredIds
     */
    private function assertFeaturedBelongToCampaignLayout(CmsCampaign $campaign, array $featuredIds): void
    {
        if ($campaign->cms_homepage_layout_id === null) {
            throw ValidationException::withMessages([
                'cms_homepage_layout_id' => ['Attach a homepage layout before attaching featured content.'],
            ]);
        }

        foreach (array_unique($featuredIds) as $id) {
            $featured = CmsFeaturedContent::query()->with('section')->find($id);
            if ($featured === null) {
                throw ValidationException::withMessages([
                    'featured_content_ids' => ["Featured content {$id} does not exist."],
                ]);
            }
            if ($featured->section?->cms_homepage_layout_id !== $campaign->cms_homepage_layout_id) {
                throw ValidationException::withMessages([
                    'featured_content_ids' => [
                        "Featured content {$id} does not belong to the campaign homepage layout.",
                    ],
                ]);
            }
        }
    }

    private function clearDefaultForContext(CmsCommerceContext $context, ?string $exceptId = null): void
    {
        $query = CmsCampaign::query()
            ->where('commerce_context', $context->value)
            ->where(function ($q) use ($context) {
                $q->where('is_default', true)->orWhere('default_slot', $context->value);
            });

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->lockForUpdate()->get()->each(function (CmsCampaign $other) {
            $other->forceFill([
                'is_default' => false,
                'default_slot' => null,
            ])->save();
        });
    }
}
