<?php

namespace App\Services\CMS;

use App\DTOs\CMS\CreateHomepageLayoutData;
use App\DTOs\CMS\CreateHomepageSectionData;
use App\DTOs\CMS\ReorderHomepageSectionsData;
use App\DTOs\CMS\UpdateHomepageLayoutData;
use App\DTOs\CMS\UpdateHomepageSectionData;
use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsHomepageSectionType;
use App\Enums\CMS\CmsStatus;
use App\Events\Audit\CmsPlatformAudit;
use App\Models\Admin;
use App\Models\CmsCampaign;
use App\Models\CmsHomepageLayout;
use App\Models\CmsHomepageSection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Experience Platform CMS — homepage layout/section orchestration.
 * Does not own products, stores, promotions, media, or pages.
 */
class CmsHomepageService
{
    public function __construct(
        private readonly CmsCampaignService $campaigns,
    ) {}

    /**
     * @param  array{status?: string, commerce_context?: string, search?: string}  $filters
     */
    public function paginateLayouts(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = CmsHomepageLayout::query()->withCount('sections')->latest();

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

    public function showLayout(CmsHomepageLayout $layout): CmsHomepageLayout
    {
        return $layout->load(['sections', 'creator']);
    }

    public function createLayout(CreateHomepageLayoutData $data, ?Admin $admin = null): CmsHomepageLayout
    {
        return DB::transaction(function () use ($data, $admin) {
            if ($data->status === CmsStatus::Archived && $data->isDefault) {
                throw ValidationException::withMessages([
                    'is_default' => ['An archived layout cannot be marked as default.'],
                ]);
            }

            if ($data->isDefault) {
                $this->clearDefaultForContext($data->commerceContext);
            }

            $layout = CmsHomepageLayout::query()->create([
                'name' => $data->name,
                'slug' => $data->slug,
                'commerce_context' => $data->commerceContext,
                'status' => $data->status,
                'is_default' => $data->isDefault,
                'default_slot' => $data->isDefault ? $data->commerceContext->value : null,
                'created_by' => $admin?->id,
            ]);

            event(CmsPlatformAudit::layoutCreated($layout, $admin));

            return $layout->fresh(['sections']) ?? $layout;
        });
    }

    public function updateLayout(
        CmsHomepageLayout $layout,
        UpdateHomepageLayoutData $data,
        ?Admin $admin = null,
    ): CmsHomepageLayout {
        return DB::transaction(function () use ($layout, $data, $admin) {
            $layout = CmsHomepageLayout::query()->whereKey($layout->id)->lockForUpdate()->firstOrFail();

            $old = [
                'name' => $layout->name,
                'slug' => $layout->slug,
                'commerce_context' => $layout->commerce_context->value,
                'status' => $layout->status->value,
                'is_default' => $layout->is_default,
            ];

            if ($data->has('name')) {
                $layout->name = $data->name;
            }
            if ($data->has('slug')) {
                $layout->slug = $data->slug;
            }

            $context = $layout->commerce_context;
            if ($data->has('commerce_context') && $data->commerceContext !== null) {
                $context = $data->commerceContext;
                $layout->commerce_context = $context;
            }

            $status = $layout->status;
            if ($data->has('status') && $data->status !== null) {
                $status = $data->status;
            }

            $wantsDefault = $data->has('is_default') ? (bool) $data->isDefault : $layout->is_default;

            if ($status === CmsStatus::Archived && $wantsDefault) {
                throw ValidationException::withMessages([
                    'status' => ['A default layout cannot be archived. Set another default first.'],
                ]);
            }

            if ($status === CmsStatus::Archived && $layout->is_default) {
                throw ValidationException::withMessages([
                    'status' => ['A default layout cannot be archived. Set another default first.'],
                ]);
            }

            $layout->status = $status;

            if ($wantsDefault) {
                $this->clearDefaultForContext($context, exceptId: $layout->id);
                $layout->is_default = true;
                $layout->default_slot = $context->value;
            } elseif ($data->has('is_default') && ! $wantsDefault) {
                $layout->is_default = false;
                $layout->default_slot = null;
            } elseif ($layout->is_default && $data->has('commerce_context')) {
                // Context changed while remaining default — move the unique slot.
                $this->clearDefaultForContext($context, exceptId: $layout->id);
                $layout->default_slot = $context->value;
            }

            $layout->save();

            if ($data->has('status') && $status === CmsStatus::Active && $old['status'] !== CmsStatus::Active->value) {
                event(CmsPlatformAudit::layoutActivated($layout, $admin));
            } else {
                event(CmsPlatformAudit::layoutUpdated($layout, $admin, $old));
            }

            return $layout->fresh(['sections']) ?? $layout;
        });
    }

    public function setDefault(CmsHomepageLayout $layout, ?Admin $admin = null): CmsHomepageLayout
    {
        return DB::transaction(function () use ($layout, $admin) {
            $layout = CmsHomepageLayout::query()->whereKey($layout->id)->lockForUpdate()->firstOrFail();

            if ($layout->status === CmsStatus::Archived) {
                throw ValidationException::withMessages([
                    'layout' => ['Archived layouts cannot be set as default.'],
                ]);
            }

            $this->clearDefaultForContext($layout->commerce_context, exceptId: $layout->id);

            $layout->forceFill([
                'is_default' => true,
                'default_slot' => $layout->commerce_context->value,
                'status' => $layout->status === CmsStatus::Draft ? CmsStatus::Active : $layout->status,
            ])->save();

            event(CmsPlatformAudit::layoutSetDefault($layout, $admin));

            return $layout->fresh(['sections']) ?? $layout;
        });
    }

    public function archiveLayout(CmsHomepageLayout $layout, ?Admin $admin = null): CmsHomepageLayout
    {
        return DB::transaction(function () use ($layout, $admin) {
            $layout = CmsHomepageLayout::query()->whereKey($layout->id)->lockForUpdate()->firstOrFail();

            if ($layout->is_default) {
                throw ValidationException::withMessages([
                    'layout' => ['A default layout cannot be archived. Set another default first.'],
                ]);
            }

            $layout->forceFill([
                'status' => CmsStatus::Archived,
                'is_default' => false,
                'default_slot' => null,
            ])->save();

            event(CmsPlatformAudit::layoutArchived($layout, $admin));

            return $layout->fresh(['sections']) ?? $layout;
        });
    }

    /**
     * @return Collection<int, CmsHomepageSection>
     */
    public function listSections(CmsHomepageLayout $layout): Collection
    {
        return $layout->sections()->orderBy('position')->orderBy('id')->get();
    }

    public function createSection(
        CmsHomepageLayout $layout,
        CreateHomepageSectionData $data,
        ?Admin $admin = null,
    ): CmsHomepageSection {
        return DB::transaction(function () use ($layout, $data, $admin) {
            $this->assertConfigurationCompatible($layout->commerce_context, $data->configuration);

            if ($data->position < 0) {
                throw ValidationException::withMessages([
                    'position' => ['Position must be a non-negative integer.'],
                ]);
            }

            $section = CmsHomepageSection::query()->create([
                'cms_homepage_layout_id' => $layout->id,
                'section_type' => $data->sectionType,
                'title' => $data->title,
                'subtitle' => $data->subtitle,
                'position' => $data->position,
                'is_visible' => $data->isVisible,
                'configuration' => $data->configuration,
                'created_by' => $admin?->id,
            ]);

            event(CmsPlatformAudit::sectionCreated($section, $admin));

            return $section->fresh(['layout']) ?? $section;
        });
    }

    public function updateSection(
        CmsHomepageSection $section,
        UpdateHomepageSectionData $data,
        ?Admin $admin = null,
    ): CmsHomepageSection {
        return DB::transaction(function () use ($section, $data, $admin) {
            $section = CmsHomepageSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();
            $layout = $section->layout()->firstOrFail();

            $oldVisible = $section->is_visible;

            if ($data->has('section_type') && $data->sectionType !== null) {
                $section->section_type = $data->sectionType;
            }
            if ($data->has('title')) {
                $section->title = $data->title;
            }
            if ($data->has('subtitle')) {
                $section->subtitle = $data->subtitle;
            }
            if ($data->has('position') && $data->position !== null) {
                if ($data->position < 0) {
                    throw ValidationException::withMessages([
                        'position' => ['Position must be a non-negative integer.'],
                    ]);
                }
                $section->position = $data->position;
            }
            if ($data->has('is_visible') && $data->isVisible !== null) {
                $section->is_visible = $data->isVisible;
            }
            if ($data->has('configuration') && $data->configuration !== null) {
                $this->assertConfigurationCompatible($layout->commerce_context, $data->configuration);
                $section->configuration = $data->configuration;
            }

            $section->save();

            if ($data->has('is_visible') && $oldVisible !== $section->is_visible) {
                event(CmsPlatformAudit::sectionVisibilityChanged($section, $admin));
            } else {
                event(CmsPlatformAudit::sectionUpdated($section, $admin));
            }

            return $section->fresh(['layout']) ?? $section;
        });
    }

    public function toggleSectionVisibility(
        CmsHomepageSection $section,
        ?Admin $admin = null,
    ): CmsHomepageSection {
        return DB::transaction(function () use ($section, $admin) {
            $section = CmsHomepageSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();
            $section->is_visible = ! $section->is_visible;
            $section->save();

            event(CmsPlatformAudit::sectionVisibilityChanged($section, $admin));

            return $section->fresh(['layout']) ?? $section;
        });
    }

    /**
     * Reorder rule: `section_ids` must be the complete unique set of this layout's
     * section IDs (no duplicates, no foreign IDs, no omissions). Positions become 0..n-1.
     *
     * @return Collection<int, CmsHomepageSection>
     */
    public function reorderSections(
        CmsHomepageLayout $layout,
        ReorderHomepageSectionsData $data,
        ?Admin $admin = null,
    ): Collection {
        return DB::transaction(function () use ($layout, $data, $admin) {
            $layout = CmsHomepageLayout::query()->whereKey($layout->id)->lockForUpdate()->firstOrFail();

            $ids = $data->sectionIds;
            if ($ids === []) {
                throw ValidationException::withMessages([
                    'section_ids' => ['At least one section id is required.'],
                ]);
            }

            if (count($ids) !== count(array_unique($ids))) {
                throw ValidationException::withMessages([
                    'section_ids' => ['Duplicate section ids are not allowed.'],
                ]);
            }

            $existingIds = $layout->sections()->pluck('id')->all();
            sort($existingIds);
            $sortedSubmitted = $ids;
            sort($sortedSubmitted);

            if ($existingIds !== $sortedSubmitted) {
                $foreign = array_values(array_diff($ids, $existingIds));
                if ($foreign !== []) {
                    throw ValidationException::withMessages([
                        'section_ids' => ['All section ids must belong to this layout.'],
                    ]);
                }

                throw ValidationException::withMessages([
                    'section_ids' => ['Reorder must include every section for this layout exactly once.'],
                ]);
            }

            foreach ($ids as $index => $sectionId) {
                CmsHomepageSection::query()
                    ->where('cms_homepage_layout_id', $layout->id)
                    ->whereKey($sectionId)
                    ->update(['position' => $index]);
            }

            $sections = $layout->sections()->orderBy('position')->orderBy('id')->get();
            event(CmsPlatformAudit::sectionsReordered($layout, $admin, $ids));

            return $sections;
        });
    }

    public function deleteSection(CmsHomepageSection $section, ?Admin $admin = null): void
    {
        DB::transaction(function () use ($section, $admin) {
            $section = CmsHomepageSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();
            $snapshot = $section->replicate();
            $snapshot->id = $section->id;
            $layoutId = $section->cms_homepage_layout_id;

            $section->delete();

            event(CmsPlatformAudit::sectionDeleted($snapshot, $layoutId, $admin));
        });
    }

    /**
     * Storefront resolution.
     *
     * Order:
     * 1. Active scheduled campaign for the exact commerce context (highest priority)
     * 2. Campaign's attached homepage layout (+ optional hero/featured curation)
     * 3. Else active default homepage layout (with optional GLOBAL layout fallback)
     *
     * Never mixes CHINA_IMPORT and TZ_LOCAL.
     *
     * @return array{layout: ?CmsHomepageLayout, campaign: ?CmsCampaign}
     */
    public function resolveStorefrontExperience(
        CmsCommerceContext $context,
        bool $allowGlobalFallback = true,
    ): array {
        $campaign = $this->campaigns->findActiveCampaign($context);
        if ($campaign !== null && $campaign->cms_homepage_layout_id !== null) {
            $layout = CmsHomepageLayout::query()->find($campaign->cms_homepage_layout_id);
            if ($layout !== null
                && $layout->status === CmsStatus::Active
                && $layout->commerce_context === $context
            ) {
                $campaign->load(['heroSlides', 'featuredContents', 'promotions']);

                return [
                    'layout' => $this->hydrateStorefrontLayout($layout, $campaign),
                    'campaign' => $campaign,
                ];
            }
        }

        $layout = $this->findActiveDefault($context);

        if ($layout === null
            && $allowGlobalFallback
            && $context !== CmsCommerceContext::Global
        ) {
            $layout = $this->findActiveDefault(CmsCommerceContext::Global);
        }

        if ($layout === null) {
            return ['layout' => null, 'campaign' => null];
        }

        return [
            'layout' => $this->hydrateStorefrontLayout($layout, null),
            'campaign' => null,
        ];
    }

    /**
     * Backward-compatible layout-only resolve (Sprint 1–3 callers).
     */
    public function resolveStorefrontLayout(
        CmsCommerceContext $context,
        bool $allowGlobalFallback = true,
    ): ?CmsHomepageLayout {
        return $this->resolveStorefrontExperience($context, $allowGlobalFallback)['layout'];
    }

    private function hydrateStorefrontLayout(
        CmsHomepageLayout $layout,
        ?CmsCampaign $campaign,
    ): CmsHomepageLayout {
        $layout->load([
            'sections' => function ($query) {
                $query->where('is_visible', true)
                    ->orderBy('position')
                    ->orderBy('id');
            },
            'sections.heroSlides' => function ($query) {
                $query->storefrontEligible()
                    ->with(['desktopMedia', 'mobileMedia'])
                    ->orderBy('position')
                    ->orderBy('id');
            },
            'sections.featuredContents' => function ($query) {
                $query->storefrontEligible()
                    ->orderBy('position')
                    ->orderBy('id');
            },
        ]);

        $heroAllow = $campaign !== null && $campaign->heroSlides->isNotEmpty()
            ? $campaign->heroSlides->pluck('id')->all()
            : null;
        $featuredAllow = $campaign !== null && $campaign->featuredContents->isNotEmpty()
            ? $campaign->featuredContents->pluck('id')->all()
            : null;

        foreach ($layout->sections as $section) {
            if ($section->section_type !== CmsHomepageSectionType::Hero) {
                $section->unsetRelation('heroSlides');
            } elseif ($heroAllow !== null && $section->relationLoaded('heroSlides')) {
                $section->setRelation(
                    'heroSlides',
                    $section->heroSlides->whereIn('id', $heroAllow)->values(),
                );
            }

            if (! ($section->section_type instanceof CmsHomepageSectionType)
                || ! $section->section_type->supportsFeaturedContent()) {
                $section->unsetRelation('featuredContents');
            } elseif ($featuredAllow !== null && $section->relationLoaded('featuredContents')) {
                $section->setRelation(
                    'featuredContents',
                    $section->featuredContents->whereIn('id', $featuredAllow)->values(),
                );
            }
        }

        return $layout;
    }

    private function findActiveDefault(CmsCommerceContext $context): ?CmsHomepageLayout
    {
        return CmsHomepageLayout::query()
            ->where('commerce_context', $context->value)
            ->where('status', CmsStatus::Active->value)
            ->where('is_default', true)
            ->first();
    }

    private function clearDefaultForContext(CmsCommerceContext $context, ?string $exceptId = null): void
    {
        $query = CmsHomepageLayout::query()
            ->where('commerce_context', $context->value)
            ->where(function ($q) use ($context) {
                $q->where('is_default', true)
                    ->orWhere('default_slot', $context->value);
            });

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->lockForUpdate()->get()->each(function (CmsHomepageLayout $other) {
            $other->forceFill([
                'is_default' => false,
                'default_slot' => null,
            ])->save();
        });
    }

    /**
     * Structural configuration checks only — no deep schemas for future modules.
     * Rejects cross-journey source hints when present on the payload.
     *
     * @param  array<string, mixed>  $configuration
     */
    private function assertConfigurationCompatible(CmsCommerceContext $layoutContext, array $configuration): void
    {
        $sourceRaw = $configuration['commerce_context']
            ?? $configuration['source_commerce_context']
            ?? null;

        if ($sourceRaw === null) {
            return;
        }

        $source = CmsCommerceContext::tryFrom((string) $sourceRaw);
        if ($source === null) {
            throw ValidationException::withMessages([
                'configuration.commerce_context' => ['Invalid commerce context in configuration.'],
            ]);
        }

        if ($layoutContext->forbidsSource($source)) {
            throw ValidationException::withMessages([
                'configuration.commerce_context' => [
                    sprintf(
                        'A %s layout cannot source %s content.',
                        $layoutContext->value,
                        $source->value,
                    ),
                ],
            ]);
        }
    }
}
