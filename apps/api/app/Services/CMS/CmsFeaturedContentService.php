<?php

namespace App\Services\CMS;

use App\DTOs\CMS\CreateFeaturedContentData;
use App\DTOs\CMS\ReorderFeaturedContentsData;
use App\DTOs\CMS\UpdateFeaturedContentData;
use App\Enums\CMS\CmsStatus;
use App\Events\Audit\CmsPlatformAudit;
use App\Models\Admin;
use App\Models\CmsFeaturedContent;
use App\Models\CmsHomepageSection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CmsFeaturedContentService
{
    public function __construct(
        private readonly CmsFeaturedContentValidationService $validator,
        private readonly CmsFeaturedContentResolver $resolver,
    ) {}

    /**
     * @return Collection<int, CmsFeaturedContent>
     */
    public function listForSection(CmsHomepageSection $section): Collection
    {
        $this->validator->assertSectionAllowsFeatured($section->section_type);

        return $section->featuredContents()->orderBy('position')->orderBy('id')->get();
    }

    public function show(CmsFeaturedContent $featured): CmsFeaturedContent
    {
        return $featured->load(['section.layout', 'creator']);
    }

    public function create(
        CmsHomepageSection $section,
        CreateFeaturedContentData $data,
        ?Admin $admin = null,
    ): CmsFeaturedContent {
        return DB::transaction(function () use ($section, $data, $admin) {
            $section = CmsHomepageSection::query()->with('layout')->whereKey($section->id)->lockForUpdate()->firstOrFail();
            $this->validator->assertSectionAllowsFeatured($section->section_type);
            $layout = $section->layout;
            if ($layout === null) {
                throw ValidationException::withMessages(['section' => ['Section has no layout.']]);
            }

            if ($data->limit < 1 || $data->limit > 48) {
                throw ValidationException::withMessages(['limit' => ['Limit must be between 1 and 48.']]);
            }
            if ($data->position < 0) {
                throw ValidationException::withMessages(['position' => ['Position must be non-negative.']]);
            }

            $this->validator->assertConfiguration(
                $data->sourceType,
                $data->configuration,
                $layout->commerce_context,
                $section->section_type,
            );

            if ($data->status === CmsStatus::Active && $layout->status === CmsStatus::Archived) {
                throw ValidationException::withMessages([
                    'status' => ['Cannot publish featured content on an archived layout.'],
                ]);
            }

            $featured = CmsFeaturedContent::query()->create([
                'cms_homepage_section_id' => $section->id,
                'title' => $data->title,
                'subtitle' => $data->subtitle,
                'source_type' => $data->sourceType,
                'limit' => $data->limit,
                'sort_order' => $data->sortOrder,
                'display_style' => $data->displayStyle,
                'configuration' => $data->configuration,
                'position' => $data->position,
                'status' => $data->status,
                'is_visible' => $data->isVisible,
                'created_by' => $admin?->id,
            ]);

            event(CmsPlatformAudit::featuredContentCreated($featured, $admin));

            return $featured->fresh(['section']) ?? $featured;
        });
    }

    public function update(
        CmsFeaturedContent $featured,
        UpdateFeaturedContentData $data,
        ?Admin $admin = null,
    ): CmsFeaturedContent {
        return DB::transaction(function () use ($featured, $data, $admin) {
            $featured = CmsFeaturedContent::query()->whereKey($featured->id)->lockForUpdate()->firstOrFail();
            $section = $featured->section()->with('layout')->lockForUpdate()->firstOrFail();
            $this->validator->assertSectionAllowsFeatured($section->section_type);
            $layout = $section->layout;
            $oldVisible = $featured->is_visible;

            if ($data->has('title') && $data->title !== null) {
                $featured->title = $data->title;
            }
            if ($data->has('subtitle')) {
                $featured->subtitle = $data->subtitle;
            }
            if ($data->has('source_type') && $data->sourceType !== null) {
                $featured->source_type = $data->sourceType;
            }
            if ($data->has('limit') && $data->limit !== null) {
                if ($data->limit < 1 || $data->limit > 48) {
                    throw ValidationException::withMessages(['limit' => ['Limit must be between 1 and 48.']]);
                }
                $featured->limit = $data->limit;
            }
            if ($data->has('sort_order') && $data->sortOrder !== null) {
                $featured->sort_order = $data->sortOrder;
            }
            if ($data->has('display_style') && $data->displayStyle !== null) {
                $featured->display_style = $data->displayStyle;
            }
            if ($data->has('configuration') && $data->configuration !== null) {
                $featured->configuration = $data->configuration;
            }
            if ($data->has('position') && $data->position !== null) {
                if ($data->position < 0) {
                    throw ValidationException::withMessages(['position' => ['Position must be non-negative.']]);
                }
                $featured->position = $data->position;
            }
            if ($data->has('status') && $data->status !== null) {
                $featured->status = $data->status;
            }
            if ($data->has('is_visible') && $data->isVisible !== null) {
                $featured->is_visible = $data->isVisible;
            }

            $this->validator->assertConfiguration(
                $featured->source_type,
                $featured->configuration ?? [],
                $layout->commerce_context,
                $section->section_type,
            );

            if ($featured->status === CmsStatus::Active && $layout->status === CmsStatus::Archived) {
                throw ValidationException::withMessages([
                    'status' => ['Cannot publish featured content on an archived layout.'],
                ]);
            }

            $featured->save();

            if ($data->has('is_visible') && $oldVisible !== $featured->is_visible) {
                event(CmsPlatformAudit::featuredContentVisibilityChanged($featured, $admin));
            } else {
                event(CmsPlatformAudit::featuredContentUpdated($featured, $admin));
            }

            return $featured->fresh(['section']) ?? $featured;
        });
    }

    public function toggleVisibility(CmsFeaturedContent $featured, ?Admin $admin = null): CmsFeaturedContent
    {
        return DB::transaction(function () use ($featured, $admin) {
            $featured = CmsFeaturedContent::query()->whereKey($featured->id)->lockForUpdate()->firstOrFail();
            $featured->is_visible = ! $featured->is_visible;
            $featured->save();
            event(CmsPlatformAudit::featuredContentVisibilityChanged($featured, $admin));

            return $featured->fresh(['section']) ?? $featured;
        });
    }

    /**
     * @return Collection<int, CmsFeaturedContent>
     */
    public function reorder(
        CmsHomepageSection $section,
        ReorderFeaturedContentsData $data,
        ?Admin $admin = null,
    ): Collection {
        return DB::transaction(function () use ($section, $data, $admin) {
            $section = CmsHomepageSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();
            $this->validator->assertSectionAllowsFeatured($section->section_type);

            $ids = $data->featuredContentIds;
            if ($ids === []) {
                throw ValidationException::withMessages([
                    'featured_content_ids' => ['At least one id is required.'],
                ]);
            }
            if (count($ids) !== count(array_unique($ids))) {
                throw ValidationException::withMessages([
                    'featured_content_ids' => ['Duplicate ids are not allowed.'],
                ]);
            }

            $existing = $section->featuredContents()->pluck('id')->all();
            sort($existing);
            $sorted = $ids;
            sort($sorted);
            if ($existing !== $sorted) {
                if (array_diff($ids, $existing) !== []) {
                    throw ValidationException::withMessages([
                        'featured_content_ids' => ['All ids must belong to this section.'],
                    ]);
                }
                throw ValidationException::withMessages([
                    'featured_content_ids' => ['Reorder must include every featured content for this section exactly once.'],
                ]);
            }

            foreach ($ids as $index => $id) {
                CmsFeaturedContent::query()
                    ->where('cms_homepage_section_id', $section->id)
                    ->whereKey($id)
                    ->update(['position' => $index]);
            }

            $items = $section->featuredContents()->orderBy('position')->orderBy('id')->get();
            event(CmsPlatformAudit::featuredContentsReordered($section, $admin, $ids));

            return $items;
        });
    }

    public function delete(CmsFeaturedContent $featured, ?Admin $admin = null): void
    {
        DB::transaction(function () use ($featured, $admin) {
            $featured = CmsFeaturedContent::query()->whereKey($featured->id)->lockForUpdate()->firstOrFail();
            $snapshot = $featured->replicate();
            $snapshot->id = $featured->id;
            $sectionId = $featured->cms_homepage_section_id;
            $featured->delete();
            event(CmsPlatformAudit::featuredContentDeleted($snapshot, $sectionId, $admin));
        });
    }

    /**
     * @return list<array{item_type: string, id: string, entity: mixed}>
     */
    public function resolveItems(CmsFeaturedContent $featured): array
    {
        $featured->loadMissing('section.layout');
        $layout = $featured->section?->layout;
        if ($layout === null) {
            return [];
        }

        return $this->resolver->resolve($featured, $layout);
    }
}
