<?php

namespace App\Services\CMS;

use App\DTOs\CMS\CreateHeroSlideData;
use App\DTOs\CMS\ReorderHeroSlidesData;
use App\DTOs\CMS\UpdateHeroSlideData;
use App\Enums\CMS\CmsHomepageSectionType;
use App\Enums\CMS\CmsStatus;
use App\Events\Audit\CmsPlatformAudit;
use App\Models\Admin;
use App\Models\CmsHeroSlide;
use App\Models\CmsHomepageLayout;
use App\Models\CmsHomepageSection;
use App\Models\Media;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hero Experience Engine — first-class slides under HERO sections.
 */
class CmsHeroSlideService
{
    public function __construct(
        private readonly CmsCtaTargetValidationService $ctaTargets,
    ) {}

    /**
     * @return Collection<int, CmsHeroSlide>
     */
    public function listForSection(CmsHomepageSection $section): Collection
    {
        $this->assertHeroSection($section);

        return $section->heroSlides()
            ->with(['desktopMedia', 'mobileMedia'])
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function show(CmsHeroSlide $slide): CmsHeroSlide
    {
        return $slide->load(['desktopMedia', 'mobileMedia', 'section.layout', 'creator']);
    }

    public function create(
        CmsHomepageSection $section,
        CreateHeroSlideData $data,
        ?Admin $admin = null,
    ): CmsHeroSlide {
        return DB::transaction(function () use ($section, $data, $admin) {
            $section = CmsHomepageSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();
            $this->assertHeroSectionAcceptsSlides($section);
            $layout = $section->layout()->firstOrFail();

            $this->assertSchedule($data->startsAt, $data->endsAt);
            $this->assertMedia($data->desktopMediaId, 'desktop_media_id');
            $this->assertMedia($data->mobileMediaId, 'mobile_media_id');
            $this->assertPosition($data->position);
            $this->assertCtas($data, $layout);

            if ($data->status === CmsStatus::Active && $layout->status === CmsStatus::Archived) {
                throw ValidationException::withMessages([
                    'status' => ['Cannot publish hero slides on an archived layout.'],
                ]);
            }

            $slide = CmsHeroSlide::query()->create([
                'cms_homepage_section_id' => $section->id,
                'name' => $data->name,
                'headline' => $data->headline,
                'subheadline' => $data->subheadline,
                'eyebrow_text' => $data->eyebrowText,
                'description' => $data->description,
                'desktop_media_id' => $data->desktopMediaId,
                'mobile_media_id' => $data->mobileMediaId,
                'content_alignment' => $data->contentAlignment,
                'text_theme' => $data->textTheme,
                'primary_cta_label' => $data->primaryCtaLabel,
                'primary_cta_type' => $data->primaryCtaType,
                'primary_cta_value' => $data->primaryCtaValue,
                'secondary_cta_label' => $data->secondaryCtaLabel,
                'secondary_cta_type' => $data->secondaryCtaType,
                'secondary_cta_value' => $data->secondaryCtaValue,
                'position' => $data->position,
                'status' => $data->status,
                'is_visible' => $data->isVisible,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'created_by' => $admin?->id,
            ]);

            event(CmsPlatformAudit::heroSlideCreated($slide, $admin));

            return $slide->fresh(['desktopMedia', 'mobileMedia']) ?? $slide;
        });
    }

    public function update(
        CmsHeroSlide $slide,
        UpdateHeroSlideData $data,
        ?Admin $admin = null,
    ): CmsHeroSlide {
        return DB::transaction(function () use ($slide, $data, $admin) {
            $slide = CmsHeroSlide::query()->whereKey($slide->id)->lockForUpdate()->firstOrFail();
            $section = $slide->section()->lockForUpdate()->firstOrFail();
            $this->assertHeroSection($section);
            $layout = $section->layout()->firstOrFail();
            $oldStatus = $slide->status;
            $oldVisible = $slide->is_visible;

            $startsAt = $data->has('starts_at') ? $data->startsAt : $slide->starts_at;
            $endsAt = $data->has('ends_at') ? $data->endsAt : $slide->ends_at;
            $this->assertSchedule($startsAt, $endsAt);

            if ($data->has('desktop_media_id')) {
                $this->assertMedia($data->desktopMediaId, 'desktop_media_id');
                $slide->desktop_media_id = $data->desktopMediaId;
            }
            if ($data->has('mobile_media_id')) {
                $this->assertMedia($data->mobileMediaId, 'mobile_media_id');
                $slide->mobile_media_id = $data->mobileMediaId;
            }
            if ($data->has('position') && $data->position !== null) {
                $this->assertPosition($data->position);
                $slide->position = $data->position;
            }

            foreach ([
                'name' => 'name',
                'headline' => 'headline',
                'subheadline' => 'subheadline',
                'eyebrow_text' => 'eyebrowText',
                'description' => 'description',
                'content_alignment' => 'contentAlignment',
                'text_theme' => 'textTheme',
                'primary_cta_label' => 'primaryCtaLabel',
                'primary_cta_type' => 'primaryCtaType',
                'primary_cta_value' => 'primaryCtaValue',
                'secondary_cta_label' => 'secondaryCtaLabel',
                'secondary_cta_type' => 'secondaryCtaType',
                'secondary_cta_value' => 'secondaryCtaValue',
                'status' => 'status',
                'is_visible' => 'isVisible',
                'starts_at' => 'startsAt',
                'ends_at' => 'endsAt',
            ] as $column => $prop) {
                if ($data->has($column) && ! in_array($column, ['desktop_media_id', 'mobile_media_id', 'position'], true)) {
                    $slide->{$column} = $data->{$prop};
                }
            }

            $status = $slide->status;
            if ($status === CmsStatus::Active && $layout->status === CmsStatus::Archived) {
                throw ValidationException::withMessages([
                    'status' => ['Cannot publish hero slides on an archived layout.'],
                ]);
            }

            $this->ctaTargets->assertCta(
                'primary_cta',
                $slide->primary_cta_type,
                $slide->primary_cta_value,
                $slide->primary_cta_label,
                $layout->commerce_context,
            );
            $this->ctaTargets->assertCta(
                'secondary_cta',
                $slide->secondary_cta_type,
                $slide->secondary_cta_value,
                $slide->secondary_cta_label,
                $layout->commerce_context,
            );

            $slide->save();

            if ($data->has('status') && $slide->status === CmsStatus::Active && $oldStatus !== CmsStatus::Active) {
                event(CmsPlatformAudit::heroSlideActivated($slide, $admin));
            } elseif ($data->has('status') && $slide->status === CmsStatus::Archived && $oldStatus !== CmsStatus::Archived) {
                event(CmsPlatformAudit::heroSlideArchived($slide, $admin));
            } elseif ($data->has('is_visible') && $oldVisible !== $slide->is_visible) {
                event(CmsPlatformAudit::heroSlideVisibilityChanged($slide, $admin));
            } else {
                event(CmsPlatformAudit::heroSlideUpdated($slide, $admin));
            }

            return $slide->fresh(['desktopMedia', 'mobileMedia']) ?? $slide;
        });
    }

    public function activate(CmsHeroSlide $slide, ?Admin $admin = null): CmsHeroSlide
    {
        return $this->update($slide, UpdateHeroSlideData::fromArray([
            'status' => CmsStatus::Active->value,
        ]), $admin);
    }

    public function archive(CmsHeroSlide $slide, ?Admin $admin = null): CmsHeroSlide
    {
        return $this->update($slide, UpdateHeroSlideData::fromArray([
            'status' => CmsStatus::Archived->value,
        ]), $admin);
    }

    public function toggleVisibility(CmsHeroSlide $slide, ?Admin $admin = null): CmsHeroSlide
    {
        return DB::transaction(function () use ($slide, $admin) {
            $slide = CmsHeroSlide::query()->whereKey($slide->id)->lockForUpdate()->firstOrFail();
            $slide->is_visible = ! $slide->is_visible;
            $slide->save();
            event(CmsPlatformAudit::heroSlideVisibilityChanged($slide, $admin));

            return $slide->fresh(['desktopMedia', 'mobileMedia']) ?? $slide;
        });
    }

    /**
     * Reorder rule: `slide_ids` must be the complete unique set for this HERO section.
     *
     * @return Collection<int, CmsHeroSlide>
     */
    public function reorder(
        CmsHomepageSection $section,
        ReorderHeroSlidesData $data,
        ?Admin $admin = null,
    ): Collection {
        return DB::transaction(function () use ($section, $data, $admin) {
            $section = CmsHomepageSection::query()->whereKey($section->id)->lockForUpdate()->firstOrFail();
            $this->assertHeroSection($section);

            $ids = $data->slideIds;
            if ($ids === []) {
                throw ValidationException::withMessages([
                    'slide_ids' => ['At least one slide id is required.'],
                ]);
            }

            if (count($ids) !== count(array_unique($ids))) {
                throw ValidationException::withMessages([
                    'slide_ids' => ['Duplicate slide ids are not allowed.'],
                ]);
            }

            $existingIds = $section->heroSlides()->pluck('id')->all();
            sort($existingIds);
            $sortedSubmitted = $ids;
            sort($sortedSubmitted);

            if ($existingIds !== $sortedSubmitted) {
                $foreign = array_values(array_diff($ids, $existingIds));
                if ($foreign !== []) {
                    throw ValidationException::withMessages([
                        'slide_ids' => ['All slide ids must belong to this HERO section.'],
                    ]);
                }

                throw ValidationException::withMessages([
                    'slide_ids' => ['Reorder must include every slide for this section exactly once.'],
                ]);
            }

            foreach ($ids as $index => $slideId) {
                CmsHeroSlide::query()
                    ->where('cms_homepage_section_id', $section->id)
                    ->whereKey($slideId)
                    ->update(['position' => $index]);
            }

            $slides = $section->heroSlides()->with(['desktopMedia', 'mobileMedia'])->orderBy('position')->orderBy('id')->get();
            event(CmsPlatformAudit::heroSlidesReordered($section, $admin, $ids));

            return $slides;
        });
    }

    public function delete(CmsHeroSlide $slide, ?Admin $admin = null): void
    {
        DB::transaction(function () use ($slide, $admin) {
            $slide = CmsHeroSlide::query()->whereKey($slide->id)->lockForUpdate()->firstOrFail();
            $snapshot = $slide->replicate();
            $snapshot->id = $slide->id;
            $sectionId = $slide->cms_homepage_section_id;
            $slide->delete();
            event(CmsPlatformAudit::heroSlideDeleted($snapshot, $sectionId, $admin));
        });
    }

    /**
     * @return Collection<int, CmsHeroSlide>
     */
    public function storefrontSlidesForSection(CmsHomepageSection $section): Collection
    {
        if ($section->section_type !== CmsHomepageSectionType::Hero || ! $section->is_visible) {
            return new Collection;
        }

        return $section->heroSlides()
            ->storefrontEligible()
            ->with(['desktopMedia', 'mobileMedia'])
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    private function assertHeroSection(CmsHomepageSection $section): void
    {
        if ($section->section_type !== CmsHomepageSectionType::Hero) {
            throw ValidationException::withMessages([
                'section' => ['Hero slides can only belong to a HERO section.'],
            ]);
        }
    }

    private function assertHeroSectionAcceptsSlides(CmsHomepageSection $section): void
    {
        $this->assertHeroSection($section);

        $layout = $section->layout;
        if ($layout !== null && $layout->status === CmsStatus::Archived) {
            throw ValidationException::withMessages([
                'section' => ['Cannot add hero slides to an archived layout.'],
            ]);
        }
    }

    private function assertCtas(CreateHeroSlideData $data, CmsHomepageLayout $layout): void
    {
        $this->ctaTargets->assertCta(
            'primary_cta',
            $data->primaryCtaType,
            $data->primaryCtaValue,
            $data->primaryCtaLabel,
            $layout->commerce_context,
        );
        $this->ctaTargets->assertCta(
            'secondary_cta',
            $data->secondaryCtaType,
            $data->secondaryCtaValue,
            $data->secondaryCtaLabel,
            $layout->commerce_context,
        );
    }

    private function assertSchedule(mixed $startsAt, mixed $endsAt): void
    {
        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            throw ValidationException::withMessages([
                'ends_at' => ['ends_at must be later than starts_at.'],
            ]);
        }
    }

    private function assertPosition(int $position): void
    {
        if ($position < 0) {
            throw ValidationException::withMessages([
                'position' => ['Position must be a non-negative integer.'],
            ]);
        }
    }

    private function assertMedia(?string $mediaId, string $field): void
    {
        if ($mediaId === null) {
            return;
        }

        $media = Media::query()->find($mediaId);
        if ($media === null) {
            throw ValidationException::withMessages([
                $field => ['Referenced media does not exist.'],
            ]);
        }

        $mime = (string) ($media->mime ?? '');
        if ($mime !== '' && ! str_starts_with($mime, 'image/')) {
            throw ValidationException::withMessages([
                $field => ['Hero media must be an image asset.'],
            ]);
        }
    }
}
