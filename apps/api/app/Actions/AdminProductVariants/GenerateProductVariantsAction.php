<?php

namespace App\Actions\AdminProductVariants;

use App\Actions\AdminProductVariants\Concerns\ResolvesVariantDefaults;
use App\Actions\Concerns\GuardsActiveProductSubResourceIntegrity;
use App\Enums\CatalogAttributeType;
use App\Http\Requests\Admin\GenerateProductVariantsRequest;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\GenerateVariantSku;
use App\Services\Catalog\SyncVariantCatalogAttributeValues;
use App\Services\Inventory\CanonicalVariantInventoryInitializer;
use App\Services\Inventory\StockResolver;
use App\Services\Pricing\CommercePricingResolver;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GenerateProductVariantsAction
{
    use GuardsActiveProductSubResourceIntegrity;
    use ResolvesVariantDefaults;

    public function __construct(
        private readonly SyncVariantCatalogAttributeValues $syncAttributeValues,
        private readonly GenerateVariantSku $generateVariantSku,
        private readonly GetProductVariantsAction $getProductVariants,
        private readonly ProductPurchasabilityPolicy $purchasabilityPolicy,
        private readonly CanonicalVariantInventoryInitializer $inventoryInitializer,
        private readonly CommercePricingResolver $pricing,
        private readonly StockResolver $stock,
    ) {}

    /**
     * @return array{
     *     variants: list<array<string, mixed>>,
     *     attributes: list<array<string, mixed>>,
     *     created_count: int,
     *     generated: int,
     *     needs_pricing: int,
     *     needs_inventory_setup: int
     * }
     */
    public function handle(GenerateProductVariantsRequest $request, Product $product): array
    {
        $product->loadMissing(['catalogProductType.attributes.options']);
        $catalogType = $product->catalogProductType;

        if ($catalogType === null) {
            throw ValidationException::withMessages([
                'catalog_product_type_id' => ['Product must have a catalog product type before generating variants.'],
            ]);
        }

        /** @var \Illuminate\Support\Collection<string, CatalogAttribute> $allowedById */
        $allowedById = $catalogType->attributes->keyBy('id');
        $payload = $request->validated('attributes') ?? [];
        $replaceExisting = (bool) $request->validated('replace_existing', false);

        $axes = [];
        $errors = [];

        foreach ($payload as $index => $row) {
            $attributeId = $row['catalog_attribute_id'];
            $attribute = $allowedById->get($attributeId);

            if ($attribute === null) {
                $errors["attributes.$index.catalog_attribute_id"] = [
                    'Attribute is not assigned to this product type.',
                ];
                continue;
            }

            $type = $attribute->type instanceof CatalogAttributeType
                ? $attribute->type
                : CatalogAttributeType::tryFrom((string) $attribute->type);

            if (! in_array($type, [CatalogAttributeType::Select, CatalogAttributeType::Multiselect], true)) {
                $errors["attributes.$index.catalog_attribute_id"] = [
                    'Only select/multiselect attributes can generate combinations.',
                ];
                continue;
            }

            $optionIds = array_values(array_unique($row['option_ids'] ?? []));
            if ($optionIds === []) {
                $errors["attributes.$index.option_ids"] = ['Select at least one option.'];
                continue;
            }

            $options = CatalogAttributeOption::query()
                ->where('catalog_attribute_id', $attribute->id)
                ->whereIn('id', $optionIds)
                ->orderBy('sort_order')
                ->get();

            if ($options->count() !== count($optionIds)) {
                $errors["attributes.$index.option_ids"] = [
                    'One or more options do not belong to this attribute.',
                ];
                continue;
            }

            $axes[] = [
                'attribute' => $attribute,
                'options' => $options->all(),
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ($axes === []) {
            throw ValidationException::withMessages([
                'attributes' => ['Provide at least one attribute with options to generate variants.'],
            ]);
        }

        $combinations = $this->cartesian($axes);
        $createdCount = 0;
        /** @var list<string> $createdVariantIds */
        $createdVariantIds = [];

        $product->load(['variants.prices', 'variants.inventories']);
        $hadSellableVariants = $this->snapshotSellableVariants($this->purchasabilityPolicy, $product);

        DB::transaction(function () use (
            $product,
            $replaceExisting,
            $combinations,
            $allowedById,
            $hadSellableVariants,
            &$createdCount,
            &$createdVariantIds,
        ) {
            if ($replaceExisting) {
                $existing = ProductVariant::query()->where('product_id', $product->id)->get();
                foreach ($existing as $variant) {
                    $variant->catalogAttributeValues()->delete();
                    $variant->delete();
                }
            }

            $existingSignatures = $this->existingSignatures($product);
            $maxSort = (int) ProductVariant::query()->where('product_id', $product->id)->max('sort_order');
            $isFirst = ! ProductVariant::query()->where('product_id', $product->id)->exists();

            foreach ($combinations as $comboIndex => $combo) {
                $signature = $this->signatureFromOptions($combo);
                if (isset($existingSignatures[$signature])) {
                    continue;
                }

                $labels = array_map(
                    fn (array $item) => $item['option']->value,
                    $combo,
                );
                $name = implode(' ', $labels);
                $sku = $this->generateVariantSku->handle($product, $labels, $comboIndex + 1);

                $variant = ProductVariant::query()->create([
                    'product_id' => $product->id,
                    'name' => $name,
                    'sku' => $sku,
                    'barcode' => null,
                    'price' => null,
                    'is_active' => true,
                    'is_default' => $isFirst && $comboIndex === 0,
                    'sort_order' => ++$maxSort,
                ]);

                $rows = array_map(fn (array $item) => [
                    'catalog_attribute_id' => $item['attribute']->id,
                    'option_id' => $item['option']->id,
                    'value_text' => $item['option']->value,
                ], $combo);

                $this->syncAttributeValues->handle($variant, $rows, $allowedById);

                // Inventory foundation only — zero stock, no invented quantity.
                $this->inventoryInitializer->ensure($variant, [
                    'warehouse_code' => 'MAIN',
                    'requested_on_hand' => 0,
                    'reason' => 'Variant generation — MAIN inventory foundation (zero stock)',
                    'idempotency_key' => 'variant-generate:'.$variant->id.':MAIN',
                ]);

                $existingSignatures[$signature] = true;
                $createdVariantIds[] = $variant->id;
                $createdCount++;
            }

            $this->ensureSingleDefault($product);

            if ($replaceExisting) {
                $this->assertActiveProductIntegrityAfterMutation(
                    $this->purchasabilityPolicy,
                    $product,
                    $hadSellableVariants,
                );
            }
        });

        $summary = $this->summarizeCreatedVariants($product, $createdVariantIds);

        $payload = $this->getProductVariants->handle($product);
        $payload['created_count'] = $createdCount;
        $payload['generated'] = $createdCount;
        $payload['needs_pricing'] = $summary['needs_pricing'];
        $payload['needs_inventory_setup'] = $summary['needs_inventory_setup'];

        return $payload;
    }

    /**
     * @param  list<string>  $createdVariantIds
     * @return array{needs_pricing: int, needs_inventory_setup: int}
     */
    private function summarizeCreatedVariants(Product $product, array $createdVariantIds): array
    {
        if ($createdVariantIds === []) {
            return [
                'needs_pricing' => 0,
                'needs_inventory_setup' => 0,
            ];
        }

        $variants = ProductVariant::query()
            ->whereIn('id', $createdVariantIds)
            ->with(['prices', 'inventories', 'product'])
            ->get();

        $needsPricing = 0;
        $needsInventorySetup = 0;

        foreach ($variants as $variant) {
            if ($this->variantNeedsPricing($variant, $product)) {
                $needsPricing++;
            }

            if ($this->variantNeedsInventorySetup($variant)) {
                $needsInventorySetup++;
            }
        }

        return [
            'needs_pricing' => $needsPricing,
            'needs_inventory_setup' => $needsInventorySetup,
        ];
    }

    private function variantNeedsPricing(ProductVariant $variant, Product $product): bool
    {
        $result = $this->pricing->resolveVariantProductPrice($variant, null, $product);

        return ! ($result->resolved && (float) $result->unitPrice > 0);
    }

    private function variantNeedsInventorySetup(ProductVariant $variant): bool
    {
        if (! $this->stock->hasVariantInventoryPolicy($variant)) {
            return true;
        }

        $resolved = $this->stock->resolveVariantProduct($variant);

        return ! $resolved->resolved || $resolved->quantityAvailable <= 0;
    }

    /**
     * @param  list<array{attribute: CatalogAttribute, options: list<CatalogAttributeOption>}>  $axes
     * @return list<list<array{attribute: CatalogAttribute, option: CatalogAttributeOption}>>
     */
    private function cartesian(array $axes): array
    {
        $result = [[]];

        foreach ($axes as $axis) {
            $next = [];
            foreach ($result as $prefix) {
                foreach ($axis['options'] as $option) {
                    $next[] = array_merge($prefix, [[
                        'attribute' => $axis['attribute'],
                        'option' => $option,
                    ]]);
                }
            }
            $result = $next;
        }

        return $result;
    }

    /**
     * @return array<string, true>
     */
    private function existingSignatures(Product $product): array
    {
        $signatures = [];

        $variants = ProductVariant::query()
            ->where('product_id', $product->id)
            ->with('catalogAttributeValues')
            ->get();

        foreach ($variants as $variant) {
            $pairs = $variant->catalogAttributeValues
                ->map(fn ($row) => $row->catalog_attribute_id.':'.($row->option_id ?? ''))
                ->sort()
                ->values()
                ->all();
            $signatures[implode('|', $pairs)] = true;
        }

        return $signatures;
    }

    /**
     * @param  list<array{attribute: CatalogAttribute, option: CatalogAttributeOption}>  $combo
     */
    private function signatureFromOptions(array $combo): string
    {
        $pairs = collect($combo)
            ->map(fn (array $item) => $item['attribute']->id.':'.$item['option']->id)
            ->sort()
            ->values()
            ->all();

        return implode('|', $pairs);
    }
}
