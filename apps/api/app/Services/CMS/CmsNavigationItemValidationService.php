<?php

namespace App\Services\CMS;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsCtaTargetType;
use App\Enums\CMS\CmsNavigationItemType;
use App\Models\CmsNavigationItem;
use App\Models\CmsNavigationShell;
use Illuminate\Validation\ValidationException;

/**
 * Validates navigation item targets without owning commerce taxonomy.
 */
class CmsNavigationItemValidationService
{
    public function __construct(private readonly CmsCtaTargetValidationService $cta) {}

    public function assertItemShape(
        CmsNavigationShell $shell,
        CmsNavigationItemType $itemType,
        ?CmsCtaTargetType $targetType,
        ?string $targetValue,
        ?string $parentId,
        ?string $exceptItemId = null,
    ): void {
        match ($itemType) {
            CmsNavigationItemType::Link => $this->assertLink($shell, $targetType, $targetValue),
            CmsNavigationItemType::Journey => $this->assertJourney($shell, $targetType, $targetValue),
            CmsNavigationItemType::MegaMenu => $this->assertMegaMenu($shell, $targetType, $targetValue),
            CmsNavigationItemType::Group => $this->assertGroup($targetType, $targetValue),
        };

        if ($parentId !== null) {
            $this->assertParentBelongsToShell($shell, $parentId, $exceptItemId);
        }
    }

    public function assertNoCircularParent(CmsNavigationItem $item, ?string $newParentId): void
    {
        if ($newParentId === null) {
            return;
        }

        if ($newParentId === $item->id) {
            throw ValidationException::withMessages([
                'parent_id' => ['An item cannot be its own parent.'],
            ]);
        }

        $cursor = CmsNavigationItem::query()->find($newParentId);
        $guard = 0;
        while ($cursor !== null && $guard < 100) {
            if ($cursor->id === $item->id) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Circular parent hierarchy is not allowed.'],
                ]);
            }
            $cursor = $cursor->parent_id
                ? CmsNavigationItem::query()->find($cursor->parent_id)
                : null;
            $guard++;
        }
    }

    private function assertLink(
        CmsNavigationShell $shell,
        ?CmsCtaTargetType $targetType,
        ?string $targetValue,
    ): void {
        if ($targetType === null || $targetType === CmsCtaTargetType::None) {
            throw ValidationException::withMessages([
                'target_type' => ['LINK items require a CTA target_type.'],
            ]);
        }

        $this->cta->assertCta('target', $targetType, $targetValue, 'link', $shell->commerce_context);
    }

    private function assertJourney(
        CmsNavigationShell $shell,
        ?CmsCtaTargetType $targetType,
        ?string $targetValue,
    ): void {
        if ($targetType !== null && $targetType !== CmsCtaTargetType::None) {
            throw ValidationException::withMessages([
                'target_type' => ['JOURNEY items must not set target_type; use target_value CHINA_IMPORT or TZ_LOCAL.'],
            ]);
        }

        $journey = $this->assertJourneyValue($targetValue);
        $this->assertJourneyCompatibleWithShell($shell->commerce_context, $journey);
    }

    private function assertMegaMenu(
        CmsNavigationShell $shell,
        ?CmsCtaTargetType $targetType,
        ?string $targetValue,
    ): void {
        if ($targetType !== null && $targetType !== CmsCtaTargetType::None) {
            throw ValidationException::withMessages([
                'target_type' => ['MEGA_MENU items must not set target_type; use target_value CHINA_IMPORT or TZ_LOCAL.'],
            ]);
        }

        $journey = $this->assertJourneyValue($targetValue);
        $this->assertJourneyCompatibleWithShell($shell->commerce_context, $journey);
    }

    private function assertGroup(?CmsCtaTargetType $targetType, ?string $targetValue): void
    {
        if ($targetType !== null && $targetType !== CmsCtaTargetType::None) {
            throw ValidationException::withMessages([
                'target_type' => ['GROUP items must not set a CTA target_type.'],
            ]);
        }
        if ($targetValue !== null && $targetValue !== '') {
            throw ValidationException::withMessages([
                'target_value' => ['GROUP items must not set target_value.'],
            ]);
        }
    }

    private function assertJourneyValue(?string $targetValue): CmsCommerceContext
    {
        if ($targetValue === null || $targetValue === '') {
            throw ValidationException::withMessages([
                'target_value' => ['target_value must be CHINA_IMPORT or TZ_LOCAL.'],
            ]);
        }

        if (! in_array($targetValue, [
            CmsCommerceContext::ChinaImport->value,
            CmsCommerceContext::TzLocal->value,
        ], true)) {
            throw ValidationException::withMessages([
                'target_value' => ['Allowed journey values are CHINA_IMPORT and TZ_LOCAL only.'],
            ]);
        }

        return CmsCommerceContext::from($targetValue);
    }

    private function assertJourneyCompatibleWithShell(
        CmsCommerceContext $shellContext,
        CmsCommerceContext $journey,
    ): void {
        if ($shellContext->forbidsSource($journey)) {
            throw ValidationException::withMessages([
                'target_value' => [
                    sprintf(
                        'Cannot mix journeys: shell context %s forbids %s.',
                        $shellContext->value,
                        $journey->value,
                    ),
                ],
            ]);
        }
    }

    private function assertParentBelongsToShell(
        CmsNavigationShell $shell,
        string $parentId,
        ?string $exceptItemId,
    ): void {
        $parent = CmsNavigationItem::query()->find($parentId);
        if ($parent === null) {
            throw ValidationException::withMessages([
                'parent_id' => ['Parent navigation item does not exist.'],
            ]);
        }

        if ($parent->navigation_shell_id !== $shell->id) {
            throw ValidationException::withMessages([
                'parent_id' => ['Parent must belong to the same navigation shell.'],
            ]);
        }

        if ($exceptItemId !== null && $parentId === $exceptItemId) {
            throw ValidationException::withMessages([
                'parent_id' => ['An item cannot be its own parent.'],
            ]);
        }
    }
}
