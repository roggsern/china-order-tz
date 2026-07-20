<?php

namespace App\Events\Audit;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Enums\CMS\CmsStatus;
use App\Models\Admin;
use App\Models\CmsCampaign;
use App\Models\CmsFeaturedContent;
use App\Models\CmsHeroSlide;
use App\Models\CmsHomepageLayout;
use App\Models\CmsHomepageSection;
use App\Models\CmsNavigationItem;
use App\Models\CmsNavigationShell;

class CmsPlatformAudit extends BusinessAuditEvent
{
    public static function layoutCreated(CmsHomepageLayout $layout, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsHomepageLayoutCreated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsHomepageLayout::class,
            subjectId: $layout->id,
            description: 'CMS homepage layout created: '.$layout->name,
            newValues: [
                'slug' => $layout->slug,
                'commerce_context' => $layout->commerce_context->value,
                'status' => $layout->status->value,
                'is_default' => $layout->is_default,
            ],
        );
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     */
    public static function layoutUpdated(
        CmsHomepageLayout $layout,
        ?Admin $admin = null,
        ?array $oldValues = null,
    ): self {
        return self::make(
            type: ActivityEventType::CmsHomepageLayoutUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsHomepageLayout::class,
            subjectId: $layout->id,
            description: 'CMS homepage layout updated: '.$layout->name,
            oldValues: $oldValues,
            newValues: [
                'slug' => $layout->slug,
                'commerce_context' => $layout->commerce_context->value,
                'status' => $layout->status->value,
                'is_default' => $layout->is_default,
            ],
        );
    }

    public static function layoutSetDefault(CmsHomepageLayout $layout, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsHomepageLayoutSetDefault,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsHomepageLayout::class,
            subjectId: $layout->id,
            description: 'CMS homepage layout set as default: '.$layout->name,
            newValues: [
                'commerce_context' => $layout->commerce_context->value,
                'is_default' => true,
            ],
        );
    }

    public static function layoutActivated(CmsHomepageLayout $layout, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsHomepageLayoutActivated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsHomepageLayout::class,
            subjectId: $layout->id,
            description: 'CMS homepage layout activated: '.$layout->name,
            newValues: [
                'status' => $layout->status->value,
            ],
        );
    }

    public static function layoutArchived(CmsHomepageLayout $layout, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsHomepageLayoutArchived,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsHomepageLayout::class,
            subjectId: $layout->id,
            description: 'CMS homepage layout archived: '.$layout->name,
            newValues: [
                'status' => $layout->status->value,
            ],
        );
    }

    public static function sectionCreated(CmsHomepageSection $section, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsHomepageSectionCreated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsHomepageSection::class,
            subjectId: $section->id,
            description: 'CMS homepage section created',
            newValues: [
                'cms_homepage_layout_id' => $section->cms_homepage_layout_id,
                'section_type' => $section->section_type instanceof \BackedEnum
                    ? $section->section_type->value
                    : $section->section_type,
                'position' => $section->position,
            ],
        );
    }

    public static function sectionUpdated(CmsHomepageSection $section, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsHomepageSectionUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsHomepageSection::class,
            subjectId: $section->id,
            description: 'CMS homepage section updated',
            newValues: [
                'section_type' => $section->section_type instanceof \BackedEnum
                    ? $section->section_type->value
                    : $section->section_type,
                'position' => $section->position,
                'is_visible' => $section->is_visible,
            ],
        );
    }

    public static function sectionVisibilityChanged(CmsHomepageSection $section, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsHomepageSectionVisibilityChanged,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsHomepageSection::class,
            subjectId: $section->id,
            description: 'CMS homepage section visibility changed',
            newValues: [
                'is_visible' => $section->is_visible,
            ],
        );
    }

    /**
     * @param  list<string>  $orderedIds
     */
    public static function sectionsReordered(
        CmsHomepageLayout $layout,
        ?Admin $admin = null,
        array $orderedIds = [],
    ): self {
        return self::make(
            type: ActivityEventType::CmsHomepageSectionsReordered,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsHomepageLayout::class,
            subjectId: $layout->id,
            description: 'CMS homepage sections reordered',
            newValues: [
                'section_ids' => $orderedIds,
            ],
        );
    }

    public static function sectionDeleted(
        CmsHomepageSection $section,
        string $layoutId,
        ?Admin $admin = null,
    ): self {
        return self::make(
            type: ActivityEventType::CmsHomepageSectionDeleted,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsHomepageSection::class,
            subjectId: $section->id,
            description: 'CMS homepage section deleted',
            oldValues: [
                'cms_homepage_layout_id' => $layoutId,
                'section_type' => $section->section_type instanceof \BackedEnum
                    ? $section->section_type->value
                    : $section->section_type,
            ],
        );
    }

    public static function heroSlideCreated(CmsHeroSlide $slide, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsHeroSlideCreated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsHeroSlide::class,
            subjectId: $slide->id,
            description: 'CMS hero slide created: '.$slide->name,
            newValues: [
                'cms_homepage_section_id' => $slide->cms_homepage_section_id,
                'status' => $slide->status instanceof \BackedEnum ? $slide->status->value : $slide->status,
                'position' => $slide->position,
            ],
        );
    }

    public static function heroSlideUpdated(CmsHeroSlide $slide, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsHeroSlideUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsHeroSlide::class,
            subjectId: $slide->id,
            description: 'CMS hero slide updated: '.$slide->name,
            newValues: [
                'status' => $slide->status instanceof \BackedEnum ? $slide->status->value : $slide->status,
                'position' => $slide->position,
                'is_visible' => $slide->is_visible,
            ],
        );
    }

    public static function heroSlideActivated(CmsHeroSlide $slide, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsHeroSlideActivated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsHeroSlide::class,
            subjectId: $slide->id,
            description: 'CMS hero slide activated: '.$slide->name,
            newValues: ['status' => CmsStatus::Active->value],
        );
    }

    public static function heroSlideArchived(CmsHeroSlide $slide, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsHeroSlideArchived,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsHeroSlide::class,
            subjectId: $slide->id,
            description: 'CMS hero slide archived: '.$slide->name,
            newValues: ['status' => CmsStatus::Archived->value],
        );
    }

    public static function heroSlideVisibilityChanged(CmsHeroSlide $slide, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsHeroSlideVisibilityChanged,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsHeroSlide::class,
            subjectId: $slide->id,
            description: 'CMS hero slide visibility changed: '.$slide->name,
            newValues: ['is_visible' => $slide->is_visible],
        );
    }

    /**
     * @param  list<string>  $orderedIds
     */
    public static function heroSlidesReordered(
        CmsHomepageSection $section,
        ?Admin $admin = null,
        array $orderedIds = [],
    ): self {
        return self::make(
            type: ActivityEventType::CmsHeroSlidesReordered,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsHomepageSection::class,
            subjectId: $section->id,
            description: 'CMS hero slides reordered',
            newValues: ['slide_ids' => $orderedIds],
        );
    }

    public static function heroSlideDeleted(
        CmsHeroSlide $slide,
        string $sectionId,
        ?Admin $admin = null,
    ): self {
        return self::make(
            type: ActivityEventType::CmsHeroSlideDeleted,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsHeroSlide::class,
            subjectId: $slide->id,
            description: 'CMS hero slide deleted: '.$slide->name,
            oldValues: [
                'cms_homepage_section_id' => $sectionId,
                'name' => $slide->name,
            ],
        );
    }

    public static function featuredContentCreated(CmsFeaturedContent $featured, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsFeaturedContentCreated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsFeaturedContent::class,
            subjectId: $featured->id,
            description: 'CMS featured content created: '.$featured->title,
            newValues: [
                'cms_homepage_section_id' => $featured->cms_homepage_section_id,
                'source_type' => $featured->source_type instanceof \BackedEnum
                    ? $featured->source_type->value
                    : $featured->source_type,
                'status' => $featured->status instanceof \BackedEnum
                    ? $featured->status->value
                    : $featured->status,
            ],
        );
    }

    public static function featuredContentUpdated(CmsFeaturedContent $featured, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsFeaturedContentUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsFeaturedContent::class,
            subjectId: $featured->id,
            description: 'CMS featured content updated: '.$featured->title,
            newValues: [
                'source_type' => $featured->source_type instanceof \BackedEnum
                    ? $featured->source_type->value
                    : $featured->source_type,
                'status' => $featured->status instanceof \BackedEnum
                    ? $featured->status->value
                    : $featured->status,
            ],
        );
    }

    public static function featuredContentVisibilityChanged(CmsFeaturedContent $featured, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsFeaturedContentVisibilityChanged,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsFeaturedContent::class,
            subjectId: $featured->id,
            description: 'CMS featured content visibility changed: '.$featured->title,
            newValues: ['is_visible' => $featured->is_visible],
        );
    }

    /**
     * @param  list<string>  $orderedIds
     */
    public static function featuredContentsReordered(
        CmsHomepageSection $section,
        ?Admin $admin = null,
        array $orderedIds = [],
    ): self {
        return self::make(
            type: ActivityEventType::CmsFeaturedContentsReordered,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsHomepageSection::class,
            subjectId: $section->id,
            description: 'CMS featured contents reordered',
            newValues: ['featured_content_ids' => $orderedIds],
        );
    }

    public static function featuredContentDeleted(
        CmsFeaturedContent $featured,
        string $sectionId,
        ?Admin $admin = null,
    ): self {
        return self::make(
            type: ActivityEventType::CmsFeaturedContentDeleted,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsFeaturedContent::class,
            subjectId: $featured->id,
            description: 'CMS featured content deleted: '.$featured->title,
            oldValues: [
                'cms_homepage_section_id' => $sectionId,
                'title' => $featured->title,
            ],
        );
    }

    public static function campaignCreated(CmsCampaign $campaign, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsCampaignCreated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsCampaign::class,
            subjectId: $campaign->id,
            description: 'CMS campaign created: '.$campaign->name,
            newValues: [
                'slug' => $campaign->slug,
                'commerce_context' => $campaign->commerce_context->value,
                'status' => $campaign->status->value,
                'priority' => $campaign->priority,
            ],
        );
    }

    public static function campaignUpdated(CmsCampaign $campaign, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsCampaignUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsCampaign::class,
            subjectId: $campaign->id,
            description: 'CMS campaign updated: '.$campaign->name,
            newValues: [
                'status' => $campaign->status->value,
                'priority' => $campaign->priority,
                'cms_homepage_layout_id' => $campaign->cms_homepage_layout_id,
            ],
        );
    }

    public static function campaignActivated(CmsCampaign $campaign, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsCampaignActivated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsCampaign::class,
            subjectId: $campaign->id,
            description: 'CMS campaign activated: '.$campaign->name,
            newValues: ['status' => CmsStatus::Active->value],
        );
    }

    public static function campaignArchived(CmsCampaign $campaign, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsCampaignArchived,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsCampaign::class,
            subjectId: $campaign->id,
            description: 'CMS campaign archived: '.$campaign->name,
            newValues: ['status' => CmsStatus::Archived->value],
        );
    }

    public static function campaignScheduleChanged(
        CmsCampaign $campaign,
        ?Admin $admin = null,
        ?string $oldStarts = null,
        ?string $oldEnds = null,
    ): self {
        return self::make(
            type: ActivityEventType::CmsCampaignScheduleChanged,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsCampaign::class,
            subjectId: $campaign->id,
            description: 'CMS campaign schedule changed: '.$campaign->name,
            oldValues: ['starts_at' => $oldStarts, 'ends_at' => $oldEnds],
            newValues: [
                'starts_at' => $campaign->starts_at?->toIso8601String(),
                'ends_at' => $campaign->ends_at?->toIso8601String(),
            ],
        );
    }

    public static function campaignPriorityChanged(
        CmsCampaign $campaign,
        ?Admin $admin = null,
        ?int $oldPriority = null,
    ): self {
        return self::make(
            type: ActivityEventType::CmsCampaignPriorityChanged,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsCampaign::class,
            subjectId: $campaign->id,
            description: 'CMS campaign priority changed: '.$campaign->name,
            oldValues: ['priority' => $oldPriority],
            newValues: ['priority' => $campaign->priority],
        );
    }

    public static function navigationShellCreated(CmsNavigationShell $shell, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsNavigationShellCreated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsNavigationShell::class,
            subjectId: $shell->id,
            description: 'CMS navigation shell created: '.$shell->name,
            newValues: [
                'slug' => $shell->slug,
                'commerce_context' => $shell->commerce_context->value,
                'navigation_type' => $shell->navigation_type->value,
                'status' => $shell->status->value,
                'is_default' => $shell->is_default,
            ],
        );
    }

    public static function navigationShellUpdated(CmsNavigationShell $shell, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsNavigationShellUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsNavigationShell::class,
            subjectId: $shell->id,
            description: 'CMS navigation shell updated: '.$shell->name,
            newValues: [
                'status' => $shell->status->value,
                'is_default' => $shell->is_default,
                'navigation_type' => $shell->navigation_type->value,
            ],
        );
    }

    public static function navigationShellPublished(CmsNavigationShell $shell, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsNavigationShellPublished,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsNavigationShell::class,
            subjectId: $shell->id,
            description: 'CMS navigation shell published: '.$shell->name,
            newValues: ['status' => CmsStatus::Active->value],
        );
    }

    public static function navigationShellDeleted(CmsNavigationShell $shell, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsNavigationShellDeleted,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsNavigationShell::class,
            subjectId: $shell->id,
            description: 'CMS navigation shell deleted: '.$shell->name,
            oldValues: [
                'slug' => $shell->slug,
                'commerce_context' => $shell->commerce_context->value,
                'navigation_type' => $shell->navigation_type->value,
            ],
        );
    }

    public static function navigationItemCreated(CmsNavigationItem $item, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsNavigationItemCreated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsNavigationItem::class,
            subjectId: $item->id,
            description: 'CMS navigation item created: '.$item->title,
            newValues: [
                'navigation_shell_id' => $item->navigation_shell_id,
                'item_type' => $item->item_type instanceof \BackedEnum
                    ? $item->item_type->value
                    : $item->item_type,
                'position' => $item->position,
            ],
        );
    }

    public static function navigationItemUpdated(CmsNavigationItem $item, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsNavigationItemUpdated,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsNavigationItem::class,
            subjectId: $item->id,
            description: 'CMS navigation item updated: '.$item->title,
            newValues: [
                'title' => $item->title,
                'is_enabled' => $item->is_enabled,
                'position' => $item->position,
            ],
        );
    }

    public static function navigationItemEnabled(CmsNavigationItem $item, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsNavigationItemEnabled,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsNavigationItem::class,
            subjectId: $item->id,
            description: 'CMS navigation item enabled: '.$item->title,
            newValues: ['is_enabled' => true],
        );
    }

    public static function navigationItemDisabled(CmsNavigationItem $item, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsNavigationItemDisabled,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsNavigationItem::class,
            subjectId: $item->id,
            description: 'CMS navigation item disabled: '.$item->title,
            newValues: ['is_enabled' => false],
        );
    }

    public static function navigationItemDeleted(CmsNavigationItem $item, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsNavigationItemDeleted,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsNavigationItem::class,
            subjectId: $item->id,
            description: 'CMS navigation item deleted: '.$item->title,
            oldValues: [
                'navigation_shell_id' => $item->navigation_shell_id,
                'title' => $item->title,
            ],
        );
    }

    public static function navigationItemsReordered(CmsNavigationShell $shell, ?Admin $admin = null): self
    {
        return self::make(
            type: ActivityEventType::CmsNavigationItemsReordered,
            actorType: $admin ? ActivityActorType::Admin : ActivityActorType::System,
            actorId: $admin?->id,
            subjectType: CmsNavigationShell::class,
            subjectId: $shell->id,
            description: 'CMS navigation items reordered: '.$shell->name,
            newValues: ['shell_id' => $shell->id],
        );
    }
}
