<?php

namespace App\Services\CMS;

use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsNavigationItemType;
use App\Enums\CMS\CmsNavigationType;
use App\Enums\CMS\CmsNavigationVisibility;
use App\Enums\CMS\CmsStatus;
use App\Http\Resources\CustomerCategoryResource;
use App\Models\CmsCampaign;
use App\Models\CmsNavigationItem;
use App\Models\CmsNavigationShell;
use App\Services\Storefront\ChinaStorefrontCatalog;
use App\Services\Storefront\TzStorefrontCatalog;
use Illuminate\Support\Collection;

/**
 * Storefront navigation resolver.
 *
 * Order: campaign shell → default shell → hydrate → journey/mega via commerce engines.
 */
class CmsNavigationResolver
{
    public function __construct(
        private readonly CmsNavigationShellService $shells,
        private readonly CmsCtaTargetValidationService $cta,
        private readonly ChinaStorefrontCatalog $chinaCatalog,
        private readonly TzStorefrontCatalog $tzCatalog,
    ) {}

    /**
     * @param  'guest'|'authenticated'|'admin_preview'  $audience
     * @return array{
     *     commerce_context: string,
     *     navigation_type: string,
     *     shell: array<string, mixed>|null,
     *     campaign: array{id: string, name: string, slug: string, priority: int}|null,
     *     items: list<array<string, mixed>>
     * }
     */
    public function resolve(
        CmsCommerceContext $context,
        CmsNavigationType $type,
        string $audience = 'guest',
        bool $hydrateMegaMenus = true,
    ): array {
        $campaign = $this->findActiveCampaign($context);
        $shell = null;
        $campaignMeta = null;

        if ($campaign !== null) {
            $campaignShell = $campaign->navigationShells
                ->first(fn (CmsNavigationShell $s) => $s->navigation_type === $type
                    && $s->status === CmsStatus::Active
                    && $s->commerce_context === $context);

            if ($campaignShell !== null) {
                $shell = $campaignShell;
                $campaignMeta = [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'slug' => $campaign->slug,
                    'priority' => (int) $campaign->priority,
                ];
            }
        }

        $shell ??= $this->shells->findDefaultShell($context, $type);

        if ($shell === null) {
            return [
                'commerce_context' => $context->value,
                'navigation_type' => $type->value,
                'shell' => null,
                'campaign' => $campaignMeta,
                'items' => [],
            ];
        }

        $shell->load(['items' => fn ($q) => $q->orderBy('position')->orderBy('id')]);

        $items = $this->buildTree(
            $shell->items,
            $audience,
            $hydrateMegaMenus,
        );

        return [
            'commerce_context' => $context->value,
            'navigation_type' => $type->value,
            'shell' => [
                'id' => $shell->id,
                'name' => $shell->name,
                'slug' => $shell->slug,
                'is_default' => (bool) $shell->is_default,
                'status' => $shell->status->value,
            ],
            'campaign' => $campaignMeta,
            'items' => $items,
        ];
    }

    /**
     * @param  'guest'|'authenticated'|'admin_preview'  $audience
     * @return array{
     *     commerce_context: string,
     *     campaign: array{id: string, name: string, slug: string, priority: int}|null,
     *     shells: array<string, array<string, mixed>>
     * }
     */
    public function resolveAll(
        CmsCommerceContext $context,
        string $audience = 'guest',
        bool $hydrateMegaMenus = true,
    ): array {
        $shells = [];
        $campaignMeta = null;

        foreach (CmsNavigationType::cases() as $type) {
            $resolved = $this->resolve($context, $type, $audience, $hydrateMegaMenus);
            $shells[$type->value] = $resolved;
            if ($campaignMeta === null && $resolved['campaign'] !== null) {
                $campaignMeta = $resolved['campaign'];
            }
        }

        return [
            'commerce_context' => $context->value,
            'campaign' => $campaignMeta,
            'shells' => $shells,
        ];
    }

    private function findActiveCampaign(CmsCommerceContext $context): ?CmsCampaign
    {
        return CmsCampaign::query()
            ->storefrontEligible()
            ->where('commerce_context', $context->value)
            ->with(['navigationShells'])
            ->orderByDesc('priority')
            ->orderByDesc('starts_at')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @param  Collection<int, CmsNavigationItem>  $flat
     * @param  'guest'|'authenticated'|'admin_preview'  $audience
     * @return list<array<string, mixed>>
     */
    private function buildTree(Collection $flat, string $audience, bool $hydrateMegaMenus, ?string $parentId = null): array
    {
        $nodes = [];

        foreach ($flat->where('parent_id', $parentId)->values() as $item) {
            if (! $item->is_enabled) {
                continue;
            }

            /** @var CmsNavigationVisibility $visibility */
            $visibility = $item->visibility;
            if (! $visibility->visibleTo($audience)) {
                continue;
            }

            $nodes[] = $this->hydrateItem($item, $flat, $audience, $hydrateMegaMenus);
        }

        return $nodes;
    }

    /**
     * @param  Collection<int, CmsNavigationItem>  $flat
     * @param  'guest'|'authenticated'|'admin_preview'  $audience
     * @return array<string, mixed>
     */
    private function hydrateItem(
        CmsNavigationItem $item,
        Collection $flat,
        string $audience,
        bool $hydrateMegaMenus,
    ): array {
        $payload = [
            'id' => $item->id,
            'title' => $item->title,
            'icon' => $item->icon,
            'position' => (int) $item->position,
            'visibility' => $item->visibility instanceof \BackedEnum
                ? $item->visibility->value
                : $item->visibility,
            'item_type' => $item->item_type instanceof \BackedEnum
                ? $item->item_type->value
                : $item->item_type,
            'target_type' => $item->target_type instanceof \BackedEnum
                ? $item->target_type->value
                : $item->target_type,
            'target_value' => $item->target_value,
            'children' => $this->buildTree($flat, $audience, $hydrateMegaMenus, $item->id),
        ];

        $type = $item->item_type instanceof CmsNavigationItemType
            ? $item->item_type
            : CmsNavigationItemType::from((string) $item->item_type);

        if ($type === CmsNavigationItemType::Link) {
            $payload['cta'] = $this->cta->resolveForStorefront(
                $item->target_type,
                $item->title,
                $item->target_value,
            );
        }

        if ($type === CmsNavigationItemType::Journey) {
            $payload['journey'] = $this->resolveJourney($item->target_value);
        }

        if ($type === CmsNavigationItemType::MegaMenu) {
            $payload['mega_menu'] = $this->resolveMegaMenu($item->target_value, $hydrateMegaMenus);
        }

        return $payload;
    }

    /**
     * @return array{code: string, engine: string, label: string}
     */
    private function resolveJourney(?string $value): array
    {
        $code = $value === CmsCommerceContext::TzLocal->value
            ? CmsCommerceContext::TzLocal->value
            : CmsCommerceContext::ChinaImport->value;

        return [
            'code' => $code,
            'engine' => $code === CmsCommerceContext::TzLocal->value
                ? 'tz_storefront_catalog'
                : 'china_storefront_catalog',
            'label' => $code === CmsCommerceContext::TzLocal->value
                ? 'Buy from TZ'
                : 'Order from China',
        ];
    }

    /**
     * @return array{engine: string, journey: string, categories?: mixed, stores?: mixed}
     */
    private function resolveMegaMenu(?string $value, bool $hydrate): array
    {
        $journey = $value === CmsCommerceContext::TzLocal->value
            ? CmsCommerceContext::TzLocal->value
            : CmsCommerceContext::ChinaImport->value;

        if ($journey === CmsCommerceContext::TzLocal->value) {
            $payload = [
                'engine' => 'tz_storefront_catalog',
                'journey' => $journey,
            ];
            if ($hydrate) {
                $stores = $this->tzCatalog->stores();
                $payload['stores'] = $stores->map(fn ($store) => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'slug' => $store->slug,
                    'logo_url' => method_exists($store, 'logoUrl') ? $store->logoUrl() : null,
                    'storefront_featured' => (bool) ($store->storefront_featured ?? false),
                ])->values()->all();
            }

            return $payload;
        }

        $payload = [
            'engine' => 'china_storefront_catalog',
            'journey' => $journey,
        ];
        if ($hydrate) {
            $categories = $this->chinaCatalog->navigationCategories();
            $payload['categories'] = CustomerCategoryResource::collection($categories)->resolve();
        }

        return $payload;
    }
}
