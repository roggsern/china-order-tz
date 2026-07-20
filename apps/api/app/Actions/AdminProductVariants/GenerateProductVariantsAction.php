<?php

namespace App\Actions\AdminProductVariants;

use App\Actions\AdminProductVariants\Concerns\ResolvesVariantDefaults;
use App\Enums\CatalogAttributeType;
use App\Http\Requests\Admin\GenerateProductVariantsRequest;
use App\Http\Resources\AdminCatalogProductVariantResource;
use App\Models\CatalogAttribute;
use App\Models\CatalogAttributeOption;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\GenerateVariantSku;
use App\Services\Catalog\SyncVariantCatalogAttributeValues;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GenerateProductVariantsAction
{
    use ResolvesVariantDefaults;

    public function __construct(
        private readonly SyncVariantCatalogAttributeValues $syncAttributeValues,
        private readonly GenerateVariantSku $generateVariantSku,
        private readonly GetProductVariantsAction $getProductVariants,
    ) {}

    /**
     * @return array{variants: list<array<string, mixed>>, attributes: list<array<string, mixed>>, created_count: int}
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

        DB::transaction(function () use (
            $product,
            $replaceExisting,
            $combinations,
            $allowedById,
            &$createdCount,
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
                $existingSignatures[$signature] = true;
                $createdCount++;
            }

            $this->ensureSingleDefault($product);
        });

        $payload = $this->getProductVariants->handle($product);
        $payload['created_count'] = $createdCount;

        return $payload;
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
