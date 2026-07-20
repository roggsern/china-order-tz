<?php

namespace App\Services\CMS;

use App\DTOs\CMS\CreateCmsNavigationItemData;
use App\DTOs\CMS\CreateCmsNavigationShellData;
use App\DTOs\CMS\ReorderCmsNavigationItemsData;
use App\DTOs\CMS\UpdateCmsNavigationItemData;
use App\DTOs\CMS\UpdateCmsNavigationShellData;
use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsNavigationType;
use App\Enums\CMS\CmsStatus;
use App\Events\Audit\CmsPlatformAudit;
use App\Models\Admin;
use App\Models\CmsNavigationItem;
use App\Models\CmsNavigationShell;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CMS Navigation Shell Engine — storefront chrome orchestration.
 * Does not own Catalog Bible categories or Store Engine trees.
 */
class CmsNavigationShellService
{
    public function __construct(
        private readonly CmsNavigationItemValidationService $itemValidation,
    ) {}

    /**
     * @param  array{status?: string, commerce_context?: string, navigation_type?: string, search?: string}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = CmsNavigationShell::query()->withCount('items')->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['commerce_context'])) {
            $query->where('commerce_context', $filters['commerce_context']);
        }
        if (! empty($filters['navigation_type'])) {
            $query->where('navigation_type', $filters['navigation_type']);
        }
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)->orWhere('slug', 'like', $search);
            });
        }

        return $query->paginate($perPage);
    }

    public function show(CmsNavigationShell $shell): CmsNavigationShell
    {
        return $shell->load(['items.children', 'creator']);
    }

    public function create(CreateCmsNavigationShellData $data, ?Admin $admin = null): CmsNavigationShell
    {
        return DB::transaction(function () use ($data, $admin) {
            if ($data->status === CmsStatus::Archived && $data->isDefault) {
                throw ValidationException::withMessages([
                    'is_default' => ['An archived shell cannot be marked as default.'],
                ]);
            }

            if ($data->isDefault) {
                $this->clearDefault($data->commerceContext, $data->navigationType);
            }

            $shell = CmsNavigationShell::query()->create([
                'name' => $data->name,
                'slug' => $data->slug,
                'commerce_context' => $data->commerceContext,
                'navigation_type' => $data->navigationType,
                'status' => $data->status,
                'is_default' => $data->isDefault,
                'default_slot' => $data->isDefault
                    ? CmsNavigationShell::defaultSlotKey($data->commerceContext, $data->navigationType)
                    : null,
                'created_by' => $admin?->id,
            ]);

            event(CmsPlatformAudit::navigationShellCreated($shell, $admin));

            return $shell;
        });
    }

    public function update(
        CmsNavigationShell $shell,
        UpdateCmsNavigationShellData $data,
        ?Admin $admin = null,
    ): CmsNavigationShell {
        return DB::transaction(function () use ($shell, $data, $admin) {
            $shell = CmsNavigationShell::query()->whereKey($shell->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $shell->status;

            if ($data->has('name') && $data->name !== null) {
                $shell->name = $data->name;
            }
            if ($data->has('slug') && $data->slug !== null) {
                $shell->slug = $data->slug;
            }

            $context = $shell->commerce_context;
            if ($data->has('commerce_context') && $data->commerceContext !== null) {
                $context = $data->commerceContext;
                $shell->commerce_context = $context;
            }

            $type = $shell->navigation_type;
            if ($data->has('navigation_type') && $data->navigationType !== null) {
                $type = $data->navigationType;
                $shell->navigation_type = $type;
            }

            $status = $data->has('status') && $data->status !== null ? $data->status : $shell->status;
            $wantsDefault = $data->has('is_default') ? (bool) $data->isDefault : $shell->is_default;

            if ($status === CmsStatus::Archived && ($wantsDefault || $shell->is_default)) {
                throw ValidationException::withMessages([
                    'status' => ['A default navigation shell cannot be archived. Unset default first.'],
                ]);
            }
            $shell->status = $status;

            if ($wantsDefault) {
                $this->clearDefault($context, $type, exceptId: $shell->id);
                $shell->is_default = true;
                $shell->default_slot = CmsNavigationShell::defaultSlotKey($context, $type);
            } elseif ($data->has('is_default') && ! $wantsDefault) {
                $shell->is_default = false;
                $shell->default_slot = null;
            } elseif ($shell->is_default && ($data->has('commerce_context') || $data->has('navigation_type'))) {
                $this->clearDefault($context, $type, exceptId: $shell->id);
                $shell->default_slot = CmsNavigationShell::defaultSlotKey($context, $type);
            }

            $shell->save();

            if ($data->has('status') && $status === CmsStatus::Active && $oldStatus !== CmsStatus::Active) {
                event(CmsPlatformAudit::navigationShellPublished($shell, $admin));
            } elseif ($data->has('status') && $status === CmsStatus::Archived && $oldStatus !== CmsStatus::Archived) {
                event(CmsPlatformAudit::navigationShellUpdated($shell, $admin));
            } else {
                event(CmsPlatformAudit::navigationShellUpdated($shell, $admin));
            }

            return $shell->fresh(['items']) ?? $shell;
        });
    }

    public function publish(CmsNavigationShell $shell, ?Admin $admin = null): CmsNavigationShell
    {
        return $this->update($shell, UpdateCmsNavigationShellData::fromArray([
            'status' => CmsStatus::Active->value,
        ]), $admin);
    }

    public function archive(CmsNavigationShell $shell, ?Admin $admin = null): CmsNavigationShell
    {
        return DB::transaction(function () use ($shell, $admin) {
            $shell = CmsNavigationShell::query()->whereKey($shell->id)->lockForUpdate()->firstOrFail();
            if ($shell->is_default) {
                throw ValidationException::withMessages([
                    'shell' => ['A default navigation shell cannot be archived. Unset default first.'],
                ]);
            }
            $shell->forceFill([
                'status' => CmsStatus::Archived,
                'is_default' => false,
                'default_slot' => null,
            ])->save();
            event(CmsPlatformAudit::navigationShellUpdated($shell, $admin));

            return $shell;
        });
    }

    public function setDefault(CmsNavigationShell $shell, ?Admin $admin = null): CmsNavigationShell
    {
        return $this->update($shell, UpdateCmsNavigationShellData::fromArray([
            'is_default' => true,
            'status' => CmsStatus::Active->value,
        ]), $admin);
    }

    public function delete(CmsNavigationShell $shell, ?Admin $admin = null): void
    {
        DB::transaction(function () use ($shell, $admin) {
            $shell = CmsNavigationShell::query()->whereKey($shell->id)->lockForUpdate()->firstOrFail();
            if ($shell->is_default) {
                throw ValidationException::withMessages([
                    'shell' => ['A default navigation shell cannot be deleted. Unset default first.'],
                ]);
            }
            event(CmsPlatformAudit::navigationShellDeleted($shell, $admin));
            $shell->delete();
        });
    }

    public function createItem(
        CmsNavigationShell $shell,
        CreateCmsNavigationItemData $data,
        ?Admin $admin = null,
    ): CmsNavigationItem {
        return DB::transaction(function () use ($shell, $data, $admin) {
            $this->itemValidation->assertItemShape(
                $shell,
                $data->itemType,
                $data->targetType,
                $data->targetValue,
                $data->parentId,
            );

            $position = $data->position;
            if ($position <= 0) {
                $position = (int) CmsNavigationItem::query()
                    ->where('navigation_shell_id', $shell->id)
                    ->where('parent_id', $data->parentId)
                    ->max('position') + 1;
            }

            $item = CmsNavigationItem::query()->create([
                'navigation_shell_id' => $shell->id,
                'parent_id' => $data->parentId,
                'title' => $data->title,
                'icon' => $data->icon,
                'position' => max(0, $position),
                'visibility' => $data->visibility,
                'item_type' => $data->itemType,
                'target_type' => $data->targetType,
                'target_value' => $data->targetValue,
                'is_enabled' => $data->isEnabled,
            ]);

            event(CmsPlatformAudit::navigationItemCreated($item, $admin));

            return $item;
        });
    }

    public function updateItem(
        CmsNavigationShell $shell,
        CmsNavigationItem $item,
        UpdateCmsNavigationItemData $data,
        ?Admin $admin = null,
    ): CmsNavigationItem {
        return DB::transaction(function () use ($shell, $item, $data, $admin) {
            $this->assertItemBelongsToShell($shell, $item);
            $item = CmsNavigationItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($data->has('title') && $data->title !== null) {
                $item->title = $data->title;
            }
            if ($data->has('icon')) {
                $item->icon = $data->clearIcon ? null : $data->icon;
            }
            if ($data->has('position') && $data->position !== null) {
                $item->position = max(0, $data->position);
            }
            if ($data->has('visibility') && $data->visibility !== null) {
                $item->visibility = $data->visibility;
            }
            if ($data->has('item_type') && $data->itemType !== null) {
                $item->item_type = $data->itemType;
            }
            if ($data->has('target_type')) {
                $item->target_type = $data->clearTargetType ? null : $data->targetType;
            }
            if ($data->has('target_value')) {
                $item->target_value = $data->clearTargetValue ? null : $data->targetValue;
            }
            if ($data->has('is_enabled') && $data->isEnabled !== null) {
                $item->is_enabled = $data->isEnabled;
            }

            $parentId = $item->parent_id;
            if ($data->has('parent_id')) {
                $parentId = $data->clearParent ? null : $data->parentId;
                $this->itemValidation->assertNoCircularParent($item, $parentId);
                $item->parent_id = $parentId;
            }

            $this->itemValidation->assertItemShape(
                $shell,
                $item->item_type,
                $item->target_type,
                $item->target_value,
                $item->parent_id,
                $item->id,
            );

            $item->save();

            if ($data->has('is_enabled') && $data->isEnabled === true) {
                event(CmsPlatformAudit::navigationItemEnabled($item, $admin));
            } elseif ($data->has('is_enabled') && $data->isEnabled === false) {
                event(CmsPlatformAudit::navigationItemDisabled($item, $admin));
            } else {
                event(CmsPlatformAudit::navigationItemUpdated($item, $admin));
            }

            return $item->fresh() ?? $item;
        });
    }

    public function enableItem(
        CmsNavigationShell $shell,
        CmsNavigationItem $item,
        ?Admin $admin = null,
    ): CmsNavigationItem {
        return $this->updateItem($shell, $item, UpdateCmsNavigationItemData::fromArray([
            'is_enabled' => true,
        ]), $admin);
    }

    public function disableItem(
        CmsNavigationShell $shell,
        CmsNavigationItem $item,
        ?Admin $admin = null,
    ): CmsNavigationItem {
        return $this->updateItem($shell, $item, UpdateCmsNavigationItemData::fromArray([
            'is_enabled' => false,
        ]), $admin);
    }

    public function deleteItem(
        CmsNavigationShell $shell,
        CmsNavigationItem $item,
        ?Admin $admin = null,
    ): void {
        DB::transaction(function () use ($shell, $item, $admin) {
            $this->assertItemBelongsToShell($shell, $item);
            $item = CmsNavigationItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            event(CmsPlatformAudit::navigationItemDeleted($item, $admin));
            $item->delete();
        });
    }

    public function reorderItems(
        CmsNavigationShell $shell,
        ReorderCmsNavigationItemsData $data,
        ?Admin $admin = null,
    ): CmsNavigationShell {
        return DB::transaction(function () use ($shell, $data, $admin) {
            $ids = array_column($data->items, 'id');
            $existing = CmsNavigationItem::query()
                ->where('navigation_shell_id', $shell->id)
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');

            if ($existing->count() !== count(array_unique($ids))) {
                throw ValidationException::withMessages([
                    'items' => ['All reorder item ids must belong to this navigation shell.'],
                ]);
            }

            foreach ($data->items as $row) {
                /** @var CmsNavigationItem $item */
                $item = $existing[$row['id']];
                $parentId = $row['has_parent'] ? $row['parent_id'] : $item->parent_id;
                if ($row['has_parent']) {
                    $this->itemValidation->assertNoCircularParent($item, $parentId);
                    if ($parentId !== null) {
                        $this->itemValidation->assertItemShape(
                            $shell,
                            $item->item_type,
                            $item->target_type,
                            $item->target_value,
                            $parentId,
                            $item->id,
                        );
                    }
                    $item->parent_id = $parentId;
                }
                $item->position = max(0, $row['position']);
                $item->save();
            }

            event(CmsPlatformAudit::navigationItemsReordered($shell, $admin));

            return $shell->fresh(['items']) ?? $shell;
        });
    }

    public function findDefaultShell(
        CmsCommerceContext $context,
        CmsNavigationType $type,
    ): ?CmsNavigationShell {
        return CmsNavigationShell::query()
            ->where('commerce_context', $context->value)
            ->where('navigation_type', $type->value)
            ->where('status', CmsStatus::Active->value)
            ->where('is_default', true)
            ->first();
    }

    private function clearDefault(
        CmsCommerceContext $context,
        CmsNavigationType $type,
        ?string $exceptId = null,
    ): void {
        $query = CmsNavigationShell::query()
            ->where('commerce_context', $context->value)
            ->where('navigation_type', $type->value)
            ->where('is_default', true);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update([
            'is_default' => false,
            'default_slot' => null,
        ]);
    }

    private function assertItemBelongsToShell(CmsNavigationShell $shell, CmsNavigationItem $item): void
    {
        if ($item->navigation_shell_id !== $shell->id) {
            throw ValidationException::withMessages([
                'item' => ['Navigation item does not belong to this shell.'],
            ]);
        }
    }
}
