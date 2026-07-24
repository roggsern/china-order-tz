<?php

namespace App\Services\ProductShipping;

use App\Enums\CommerceChannelCode;
use App\Enums\ShippingMethod;
use App\Events\Audit\ShippingOptionUpdated;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductShippingOption;
use App\Services\Commerce\CommerceChannelResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Product Shipping Options Engine.
 * Administrators enter prices manually — the system never calculates shipping costs.
 */
class ProductShippingOptionEngine
{
    public function __construct(
        private readonly CommerceChannelResolver $commerceChannelResolver,
    ) {}
    /**
     * @return list<ProductShippingOption>
     */
    public function listForProduct(Product $product, bool $includeUnavailable = true): array
    {
        $query = $product->shippingOptions()->ordered();

        if (! $includeUnavailable) {
            $query->available();
        }

        return $query->get()->all();
    }

    /**
     * @param  array{
     *     transport_mode: string,
     *     price: float|int|string,
     *     currency?: string|null,
     *     is_available?: bool,
     *     notes?: string|null,
     *     sort_order?: int
     * }  $input
     */
    public function create(Product $product, array $input): ProductShippingOption
    {
        $this->assertChinaImportProduct($product);

        $mode = $this->resolveMode($input['transport_mode'] ?? null);

        $option = DB::transaction(function () use ($product, $mode, $input): ProductShippingOption {
            $trashed = ProductShippingOption::onlyTrashed()
                ->where('product_id', $product->id)
                ->where('transport_mode', $mode->value)
                ->first();

            if ($trashed !== null) {
                $trashed->restore();
                $trashed->fill($this->attributesFromInput($input, $mode))->save();
                $this->syncLegacyColumns($product->fresh() ?? $product);

                return $trashed->fresh() ?? $trashed;
            }

            if (
                ProductShippingOption::query()
                    ->where('product_id', $product->id)
                    ->where('transport_mode', $mode->value)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'transport_mode' => ["A {$mode->value} shipping option already exists for this product."],
                ]);
            }

            $created = ProductShippingOption::query()->create([
                'product_id' => $product->id,
                ...$this->attributesFromInput($input, $mode),
            ]);

            $this->syncLegacyColumns($product->fresh() ?? $product);

            return $created;
        });

        $admin = auth('sanctum')->user();
        event(ShippingOptionUpdated::fromOption(
            $option,
            null,
            $this->auditValues($option),
            $admin instanceof Admin ? $admin : null,
            'created',
        ));

        return $option;
    }

    /**
     * @param  array{
     *     transport_mode?: string,
     *     price?: float|int|string,
     *     currency?: string|null,
     *     is_available?: bool,
     *     notes?: string|null,
     *     sort_order?: int
     * }  $input
     */
    public function update(Product $product, ProductShippingOption $option, array $input): ProductShippingOption
    {
        $this->assertOwns($product, $option);
        $this->assertChinaImportProduct($product);

        return DB::transaction(function () use ($product, $option, $input): ProductShippingOption {
            $hadPublishableShipping = $this->snapshotHadPublishableShipping($product);

            /** @var ProductShippingOption $locked */
            $locked = ProductShippingOption::query()->whereKey($option->id)->lockForUpdate()->firstOrFail();

            $oldValues = $this->auditValues($locked);

            if (array_key_exists('transport_mode', $input) && filled($input['transport_mode'])) {
                $mode = $this->resolveMode($input['transport_mode']);
                $duplicate = ProductShippingOption::query()
                    ->where('product_id', $product->id)
                    ->where('transport_mode', $mode->value)
                    ->whereKeyNot($locked->id)
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'transport_mode' => ["A {$mode->value} shipping option already exists for this product."],
                    ]);
                }

                $locked->transport_mode = $mode;
            }

            if (array_key_exists('price', $input)) {
                $locked->price = $input['price'];
            }
            if (array_key_exists('currency', $input)) {
                $locked->currency = strtoupper((string) ($input['currency'] ?: 'TZS'));
            }
            if (array_key_exists('is_available', $input)) {
                $locked->is_available = (bool) $input['is_available'];
            }
            if (array_key_exists('notes', $input)) {
                $locked->notes = $input['notes'];
            }
            if (array_key_exists('sort_order', $input)) {
                $locked->sort_order = (int) $input['sort_order'];
            }

            $locked->save();
            $this->syncLegacyColumns($product->fresh() ?? $product);

            $fresh = $locked->fresh() ?? $locked;
            $this->assertActiveProductShippingIntegrity($product, $hadPublishableShipping);
            $admin = auth('sanctum')->user();
            event(ShippingOptionUpdated::fromOption(
                $fresh,
                $oldValues,
                $this->auditValues($fresh),
                $admin instanceof Admin ? $admin : null,
                'updated',
            ));

            return $fresh;
        });
    }

    public function delete(Product $product, ProductShippingOption $option): void
    {
        $this->assertOwns($product, $option);

        DB::transaction(function () use ($product, $option): void {
            $hadPublishableShipping = $this->snapshotHadPublishableShipping($product);

            $option->delete();
            $this->syncLegacyColumns($product->fresh() ?? $product);
            $this->assertActiveProductShippingIntegrity($product, $hadPublishableShipping);
        });
    }

    public function restore(Product $product, string $optionId): ProductShippingOption
    {
        $this->assertChinaImportProduct($product);

        /** @var ProductShippingOption $option */
        $option = ProductShippingOption::onlyTrashed()
            ->where('product_id', $product->id)
            ->whereKey($optionId)
            ->firstOrFail();

        return DB::transaction(function () use ($product, $option): ProductShippingOption {
            $option->restore();
            $this->syncLegacyColumns($product->fresh() ?? $product);

            return $option->fresh() ?? $option;
        });
    }

    /**
     * Replace product shipping options from an admin payload.
     * Empty array clears all options (soft delete).
     *
     * @param  list<array{
     *     transport_mode: string,
     *     price: float|int|string,
     *     currency?: string|null,
     *     is_available?: bool,
     *     notes?: string|null,
     *     sort_order?: int
     * }>  $rows
     * @return list<ProductShippingOption>
     */
    public function syncForProduct(Product $product, array $rows): array
    {
        $this->assertChinaImportProduct($product);

        return DB::transaction(function () use ($product, $rows): array {
            $hadPublishableShipping = $this->snapshotHadPublishableShipping($product);

            $seenModes = [];
            $keepIds = [];

            foreach ($rows as $index => $row) {
                $mode = $this->resolveMode($row['transport_mode'] ?? null);
                if (isset($seenModes[$mode->value])) {
                    throw ValidationException::withMessages([
                        "shipping_options.{$index}.transport_mode" => [
                            'Duplicate transport_mode in shipping_options payload.',
                        ],
                    ]);
                }
                $seenModes[$mode->value] = true;

                $existing = ProductShippingOption::withTrashed()
                    ->where('product_id', $product->id)
                    ->where('transport_mode', $mode->value)
                    ->first();

                $attrs = $this->attributesFromInput($row, $mode);
                if (! array_key_exists('sort_order', $row)) {
                    $attrs['sort_order'] = $mode === ShippingMethod::Air ? 0 : 1;
                }

                if ($existing !== null) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    $existing->fill($attrs)->save();
                    $keepIds[] = $existing->id;
                } else {
                    $created = ProductShippingOption::query()->create([
                        'product_id' => $product->id,
                        ...$attrs,
                    ]);
                    $keepIds[] = $created->id;
                }
            }

            ProductShippingOption::query()
                ->where('product_id', $product->id)
                ->when($keepIds !== [], fn ($q) => $q->whereNotIn('id', $keepIds))
                ->get()
                ->each(fn (ProductShippingOption $option) => $option->delete());

            $this->syncLegacyColumns($product->fresh() ?? $product);

            $options = $this->listForProduct($product->fresh() ?? $product);
            $this->assertActiveProductShippingIntegrity($product, $hadPublishableShipping);

            return $options;
        });
    }

    /**
     * Keep legacy product air/sea columns in sync for older readers.
     */
    public function syncLegacyColumns(Product $product): void
    {
        if ($this->isTzLocalProduct($product)) {
            if ($product->air_shipping_price !== null || $product->sea_shipping_price !== null) {
                $product->forceFill([
                    'air_shipping_price' => null,
                    'sea_shipping_price' => null,
                ])->save();
            }

            return;
        }

        $options = ProductShippingOption::query()
            ->where('product_id', $product->id)
            ->get()
            ->keyBy(fn (ProductShippingOption $o) => $o->transport_mode instanceof ShippingMethod
                ? $o->transport_mode->value
                : (string) $o->transport_mode);

        $air = $options->get(ShippingMethod::Air->value);
        $sea = $options->get(ShippingMethod::Sea->value);

        $product->forceFill([
            'air_shipping_price' => ($air !== null && $air->is_available) ? $air->price : null,
            'sea_shipping_price' => ($sea !== null && $sea->is_available) ? $sea->price : null,
        ])->save();
    }

    /**
     * Seed options from legacy flat columns when the product has none yet.
     */
    public function backfillFromLegacy(Product $product): void
    {
        if ($product->shippingOptions()->exists()) {
            return;
        }

        $rows = [];
        if ($product->air_shipping_price !== null) {
            $rows[] = [
                'transport_mode' => ShippingMethod::Air->value,
                'price' => $product->air_shipping_price,
                'currency' => 'TZS',
                'is_available' => true,
                'sort_order' => 0,
            ];
        }
        if ($product->sea_shipping_price !== null) {
            $rows[] = [
                'transport_mode' => ShippingMethod::Sea->value,
                'price' => $product->sea_shipping_price,
                'currency' => 'TZS',
                'is_available' => true,
                'sort_order' => 1,
            ];
        }

        if ($rows !== []) {
            $this->syncForProduct($product, $rows);
        }
    }

    /**
     * @return list<array{value: string, label: string, price: string|null, currency: string}>
     */
    public function availableMethodsForOrder(Order $order): array
    {
        $order->loadMissing(['items.product.shippingOptions']);

        $chinaProducts = $order->items
            ->map(fn ($item) => $item->product)
            ->filter(fn ($product) => $product instanceof Product && $product->requiresChinaShipping())
            ->unique('id')
            ->values();

        if ($chinaProducts->isEmpty()) {
            return [];
        }

        $intersection = null;

        foreach ($chinaProducts as $product) {
            $modes = $this->availableModeValues($product);
            $intersection = $intersection === null
                ? $modes
                : array_values(array_intersect($intersection, $modes));
        }

        $intersection ??= [];

        $labels = [
            ShippingMethod::Air->value => 'Air Freight',
            ShippingMethod::Sea->value => 'Sea Freight',
        ];

        $result = [];
        foreach ($intersection as $mode) {
            $result[] = [
                'value' => $mode,
                'label' => $labels[$mode] ?? ucfirst($mode),
                'price' => null,
                'currency' => 'TZS',
            ];
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    public function availableModeValues(Product $product): array
    {
        $fromOptions = $product->shippingOptions()
            ->available()
            ->get()
            ->map(fn (ProductShippingOption $o) => $o->transport_mode instanceof ShippingMethod
                ? $o->transport_mode->value
                : (string) $o->transport_mode)
            ->values()
            ->all();

        if ($fromOptions !== []) {
            return $fromOptions;
        }

        // Legacy fallback when options table not yet populated.
        $modes = [];
        if ($product->air_shipping_price !== null) {
            $modes[] = ShippingMethod::Air->value;
        }
        if ($product->sea_shipping_price !== null) {
            $modes[] = ShippingMethod::Sea->value;
        }

        // China products with no prices yet still show both for selection UX
        // until admins configure options — cart will reject missing prices.
        if ($modes === [] && $product->isFromChina()) {
            return [ShippingMethod::Air->value, ShippingMethod::Sea->value];
        }

        return $modes;
    }

    /**
     * Publish gate: at least one available option with a positive price.
     */
    public function hasPublishableShippingOption(Product $product): bool
    {
        if ($product->relationLoaded('shippingOptions')) {
            return $product->shippingOptions->contains(
                fn (ProductShippingOption $option) => $option->is_available && (float) $option->price > 0,
            );
        }

        return $product->shippingOptions()
            ->available()
            ->where('price', '>', 0)
            ->exists();
    }

    private function resolveMode(mixed $value): ShippingMethod
    {
        $mode = ShippingMethod::tryFrom((string) $value);
        if ($mode === null) {
            throw ValidationException::withMessages([
                'transport_mode' => ['transport_mode must be air or sea.'],
            ]);
        }

        return $mode;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function attributesFromInput(array $input, ShippingMethod $mode): array
    {
        return [
            'transport_mode' => $mode,
            'price' => $input['price'],
            'currency' => strtoupper((string) ($input['currency'] ?? 'TZS')),
            'is_available' => array_key_exists('is_available', $input)
                ? (bool) $input['is_available']
                : true,
            'notes' => $input['notes'] ?? null,
            'sort_order' => (int) ($input['sort_order'] ?? ($mode === ShippingMethod::Air ? 0 : 1)),
        ];
    }

    private function assertOwns(Product $product, ProductShippingOption $option): void
    {
        if ($option->product_id !== $product->id) {
            abort(404);
        }
    }

    private function assertChinaImportProduct(Product $product): void
    {
        if ($this->isTzLocalProduct($product)) {
            throw ValidationException::withMessages([
                'shipping' => ['China shipping options are only allowed for CHINA_IMPORT products.'],
            ]);
        }
    }

    private function isTzLocalProduct(Product $product): bool
    {
        $channel = $this->commerceChannelResolver->resolveProductChannel($product);

        return CommerceChannelCode::tryFrom((string) $channel->code) === CommerceChannelCode::TzLocal;
    }

    /**
     * @return array{price: mixed, transport_mode: mixed, currency: mixed, is_available: mixed}
     */
    private function auditValues(ProductShippingOption $option): array
    {
        return [
            'price' => $option->price,
            'transport_mode' => $option->transport_mode instanceof \BackedEnum
                ? $option->transport_mode->value
                : $option->transport_mode,
            'currency' => $option->currency,
            'is_available' => $option->is_available,
        ];
    }

    private function snapshotHadPublishableShipping(Product $product): bool
    {
        return $this->purchasabilityPolicy()->snapshotHadPublishableShippingOption(
            $product->fresh(['shippingOptions', 'commerceChannel']) ?? $product,
        );
    }

    private function assertActiveProductShippingIntegrity(
        Product $product,
        bool $hadPublishableShippingBefore,
    ): void {
        $this->purchasabilityPolicy()->assertActiveShippingIntegrityAfterMutation(
            $product->fresh(['shippingOptions', 'commerceChannel']) ?? $product,
            $hadPublishableShippingBefore,
        );
    }

    private function purchasabilityPolicy(): \App\Services\ProductPurchasability\ProductPurchasabilityPolicy
    {
        return app(\App\Services\ProductPurchasability\ProductPurchasabilityPolicy::class);
    }
}
