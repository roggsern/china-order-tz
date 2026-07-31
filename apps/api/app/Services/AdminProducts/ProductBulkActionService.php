<?php

namespace App\Services\AdminProducts;

use App\Enums\ProductLifecycleStatus;
use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Services\Inventory\AdminInventoryApplicationService;
use App\Services\Inventory\StockResolver;
use App\Services\Pricing\CommercePricingResolver;
use App\Services\Pricing\DTOs\CommercePricingContext;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Bulk product operations — reuses lifecycle, variant_prices, and inventory engines.
 */
class ProductBulkActionService
{
    public const ACTION_PUBLISH = 'publish';

    public const ACTION_ARCHIVE = 'archive';

    public const ACTION_PRICING_PERCENTAGE_INCREASE = 'pricing_percentage_increase';

    public const ACTION_PRICING_PERCENTAGE_DECREASE = 'pricing_percentage_decrease';

    public const ACTION_PRICING_FIXED = 'pricing_fixed';

    public const ACTION_INVENTORY_INCREASE = 'inventory_increase';

    public const ACTION_INVENTORY_DECREASE = 'inventory_decrease';

    public const ACTION_INVENTORY_SET = 'inventory_set';

    /** @var list<string> */
    public const ACTIONS = [
        self::ACTION_PUBLISH,
        self::ACTION_ARCHIVE,
        self::ACTION_PRICING_PERCENTAGE_INCREASE,
        self::ACTION_PRICING_PERCENTAGE_DECREASE,
        self::ACTION_PRICING_FIXED,
        self::ACTION_INVENTORY_INCREASE,
        self::ACTION_INVENTORY_DECREASE,
        self::ACTION_INVENTORY_SET,
    ];

    public function __construct(
        private readonly ProductPurchasabilityPolicy $purchasability,
        private readonly AdminInventoryApplicationService $inventory,
        private readonly StockResolver $stockResolver,
        private readonly CommercePricingResolver $pricingResolver,
    ) {}

    /**
     * @param  list<string>  $productIds
     * @param  array{percent?: float|int|null, amount?: float|int|null, quantity?: int|null}  $payload
     * @return array{
     *   batch_id: string,
     *   action_key: string,
     *   total: int,
     *   succeeded: int,
     *   failed: int,
     *   results: list<array{product_id: string, success: bool, message: string}>
     * }
     */
    public function execute(Admin $admin, string $actionKey, array $productIds, array $payload = []): array
    {
        $actionKey = strtolower(trim($actionKey));
        $productIds = array_values(array_unique(array_filter(array_map(
            static fn ($id) => is_string($id) ? trim($id) : '',
            $productIds,
        ))));

        if ($productIds === []) {
            throw ValidationException::withMessages([
                'product_ids' => ['At least one product id is required.'],
            ]);
        }

        if (! in_array($actionKey, self::ACTIONS, true)) {
            throw ValidationException::withMessages([
                'action_key' => ['Unsupported bulk action.'],
            ]);
        }

        $this->validatePayload($actionKey, $payload);

        $batchId = (string) Str::uuid();
        $results = [];

        foreach ($productIds as $productId) {
            try {
                $product = Product::query()
                    ->with([
                        'variants.prices',
                        'variants.inventories',
                        'inventory',
                        'commerceChannel',
                        'catalogProductType',
                        'category',
                        'shippingOptions',
                        'store',
                    ])
                    ->find($productId);

                if ($product === null) {
                    $results[] = $this->failure($productId, 'Product not found.');
                    continue;
                }

                $message = match ($actionKey) {
                    self::ACTION_PUBLISH => $this->publish($product),
                    self::ACTION_ARCHIVE => $this->archive($product),
                    self::ACTION_PRICING_PERCENTAGE_INCREASE,
                    self::ACTION_PRICING_PERCENTAGE_DECREASE,
                    self::ACTION_PRICING_FIXED => $this->applyPricing($product, $actionKey, $payload),
                    self::ACTION_INVENTORY_INCREASE,
                    self::ACTION_INVENTORY_DECREASE,
                    self::ACTION_INVENTORY_SET => $this->applyInventory($product, $admin, $actionKey, $payload),
                    default => throw ValidationException::withMessages([
                        'action_key' => ['Unsupported bulk action.'],
                    ]),
                };

                $results[] = $this->success($productId, $message);
            } catch (ValidationException $exception) {
                $results[] = $this->failure($productId, $this->firstValidationMessage($exception));
            } catch (Throwable $exception) {
                $results[] = $this->failure($productId, $exception->getMessage() ?: 'Unable to complete bulk action.');
            }
        }

        $succeeded = count(array_filter($results, static fn (array $row) => $row['success'] === true));

        return [
            'batch_id' => $batchId,
            'action_key' => $actionKey,
            'total' => count($results),
            'succeeded' => $succeeded,
            'failed' => count($results) - $succeeded,
            'results' => $results,
        ];
    }

    /**
     * @param  array{percent?: float|int|null, amount?: float|int|null, quantity?: int|null}  $payload
     */
    private function validatePayload(string $actionKey, array $payload): void
    {
        if (in_array($actionKey, [
            self::ACTION_PRICING_PERCENTAGE_INCREASE,
            self::ACTION_PRICING_PERCENTAGE_DECREASE,
        ], true)) {
            $percent = $payload['percent'] ?? null;
            if (! is_numeric($percent) || (float) $percent <= 0) {
                throw ValidationException::withMessages([
                    'payload.percent' => ['A positive percent value is required.'],
                ]);
            }
        }

        if ($actionKey === self::ACTION_PRICING_FIXED) {
            $amount = $payload['amount'] ?? null;
            if (! is_numeric($amount) || (float) $amount < 0) {
                throw ValidationException::withMessages([
                    'payload.amount' => ['A non-negative amount is required.'],
                ]);
            }
        }

        if (in_array($actionKey, [
            self::ACTION_INVENTORY_INCREASE,
            self::ACTION_INVENTORY_DECREASE,
            self::ACTION_INVENTORY_SET,
        ], true)) {
            $quantity = $payload['quantity'] ?? null;
            if (! is_numeric($quantity) || (int) $quantity < 0) {
                throw ValidationException::withMessages([
                    'payload.quantity' => ['A non-negative quantity is required.'],
                ]);
            }
            if (in_array($actionKey, [
                self::ACTION_INVENTORY_INCREASE,
                self::ACTION_INVENTORY_DECREASE,
            ], true) && (int) $quantity <= 0) {
                throw ValidationException::withMessages([
                    'payload.quantity' => ['Quantity must be greater than zero for increase/decrease.'],
                ]);
            }
        }
    }

    private function publish(Product $product): string
    {
        if ($product->is_demo) {
            throw ValidationException::withMessages([
                'is_demo' => ['Demo products cannot be published.'],
            ]);
        }

        $product->forceFill([
            'lifecycle_status' => ProductLifecycleStatus::Active,
            'is_active' => true,
        ])->save();

        $fresh = $product->fresh([
            'variants.prices',
            'variants.inventories',
            'inventory',
            'commerceChannel',
            'catalogProductType',
            'category',
            'shippingOptions',
            'store',
        ]) ?? $product;

        $this->purchasability->assertPublishable($fresh);

        return 'Published.';
    }

    private function archive(Product $product): string
    {
        $product->forceFill([
            'lifecycle_status' => ProductLifecycleStatus::Archived,
            'is_active' => false,
        ])->save();

        return 'Archived.';
    }

    /**
     * @param  array{percent?: float|int|null, amount?: float|int|null}  $payload
     */
    private function applyPricing(Product $product, string $actionKey, array $payload): string
    {
        $activeVariants = $product->variants
            ->filter(fn (ProductVariant $variant) => (bool) $variant->is_active)
            ->values();

        if ($activeVariants->isNotEmpty()) {
            $updated = 0;
            foreach ($activeVariants as $variant) {
                $current = $this->currentRetailAmount($variant, $product);
                $next = $this->computeNextPrice($current, $actionKey, $payload);
                $this->upsertRetailPrice($variant, $next);
                $updated++;
            }

            if ($product->lifecycle_status === ProductLifecycleStatus::Active) {
                $fresh = $product->fresh([
                    'variants.prices',
                    'variants.inventories',
                    'inventory',
                    'commerceChannel',
                    'catalogProductType',
                    'category',
                    'shippingOptions',
                    'store',
                ]);
                if ($fresh !== null) {
                    $this->purchasability->assertPublishable($fresh);
                }
            }

            return sprintf('Updated retail price on %d variant(s).', $updated);
        }

        $current = (float) ($product->price ?? 0);
        $next = $this->computeNextPrice($current, $actionKey, $payload);
        $product->forceFill(['price' => number_format($next, 2, '.', '')])->save();

        if ($product->lifecycle_status === ProductLifecycleStatus::Active) {
            $fresh = $product->fresh([
                'variants.prices',
                'variants.inventories',
                'inventory',
                'commerceChannel',
                'catalogProductType',
                'category',
                'shippingOptions',
                'store',
            ]);
            if ($fresh !== null) {
                $this->purchasability->assertPublishable($fresh);
            }
        }

        return 'Updated product price.';
    }

    /**
     * @param  array{quantity?: int|null}  $payload
     */
    private function applyInventory(Product $product, Admin $admin, string $actionKey, array $payload): string
    {
        $qty = (int) ($payload['quantity'] ?? 0);
        $activeVariants = $product->variants
            ->filter(fn (ProductVariant $variant) => (bool) $variant->is_active)
            ->values();

        if ($activeVariants->isNotEmpty()) {
            $updated = 0;
            foreach ($activeVariants as $variant) {
                $row = $this->mainInventoryRow($variant);
                $current = $row !== null
                    ? (int) $row->on_hand
                    : (int) $this->stockResolver->resolveVariantProduct($variant, null, $product)->quantityOnHand;

                $target = $this->computeNextQuantity($current, $actionKey, $qty);

                if ($row === null) {
                    if ($actionKey === self::ACTION_INVENTORY_DECREASE && $target === 0 && $current === 0) {
                        throw ValidationException::withMessages([
                            'inventory' => ["Variant {$variant->sku} has no inventory row to decrease."],
                        ]);
                    }

                    $this->inventory->createVariantInventory($variant, [
                        'warehouse_code' => 'MAIN',
                        'on_hand' => $target,
                        'reserved' => 0,
                        'is_active' => true,
                    ], $admin);
                } else {
                    $this->inventory->updateVariantInventory($row, [
                        'on_hand' => $target,
                    ], $admin);
                }

                $updated++;
            }

            return sprintf('Updated inventory on %d variant(s).', $updated);
        }

        $current = (int) $this->stockResolver->resolveSimpleProduct($product)->quantityOnHand;
        $target = $this->computeNextQuantity($current, $actionKey, $qty);
        $this->inventory->setSimpleProductStock(
            product: $product,
            targetQuantity: $target,
            actor: $admin,
            reason: 'Bulk product inventory '.$actionKey,
        );

        return 'Updated product inventory.';
    }

    /**
     * @param  array{percent?: float|int|null, amount?: float|int|null}  $payload
     */
    private function computeNextPrice(float $current, string $actionKey, array $payload): float
    {
        if ($actionKey === self::ACTION_PRICING_FIXED) {
            return round(max(0, (float) $payload['amount']), 2);
        }

        $percent = (float) $payload['percent'];
        $factor = $percent / 100;

        if ($actionKey === self::ACTION_PRICING_PERCENTAGE_INCREASE) {
            return round(max(0, $current * (1 + $factor)), 2);
        }

        return round(max(0, $current * (1 - $factor)), 2);
    }

    private function computeNextQuantity(int $current, string $actionKey, int $quantity): int
    {
        return match ($actionKey) {
            self::ACTION_INVENTORY_INCREASE => max(0, $current + $quantity),
            self::ACTION_INVENTORY_DECREASE => max(0, $current - $quantity),
            self::ACTION_INVENTORY_SET => max(0, $quantity),
            default => $current,
        };
    }

    private function currentRetailAmount(ProductVariant $variant, Product $product): float
    {
        $result = $this->pricingResolver->resolveVariantProductPrice(
            $variant,
            new CommercePricingContext(
                currency: 'TZS',
                allowLegacyVariantFallback: true,
            ),
            $product,
        );

        if (! $result->resolved || (float) $result->unitPrice <= 0) {
            return 0.0;
        }

        return (float) $result->unitPrice;
    }

    private function upsertRetailPrice(ProductVariant $variant, float $amount): void
    {
        DB::transaction(function () use ($variant, $amount): void {
            $retail = $this->activeRetailPrice($variant);
            if ($retail !== null) {
                $retail->forceFill([
                    'amount' => number_format($amount, 2, '.', ''),
                    'is_active' => true,
                ])->save();

                return;
            }

            VariantPrice::query()->create([
                'product_variant_id' => $variant->id,
                'price_type' => VariantPriceType::Retail,
                'currency' => 'TZS',
                'amount' => number_format($amount, 2, '.', ''),
                'minimum_quantity' => 1,
                'is_active' => true,
            ]);
        });
    }

    private function activeRetailPrice(ProductVariant $variant): ?VariantPrice
    {
        $now = Carbon::now();
        $prices = $variant->relationLoaded('prices')
            ? $variant->prices
            : $variant->prices()->get();

        return $prices
            ->filter(function (VariantPrice $price) use ($now): bool {
                if (! $price->is_active) {
                    return false;
                }

                $type = $price->price_type instanceof VariantPriceType
                    ? $price->price_type
                    : VariantPriceType::tryFrom((string) $price->price_type);

                if ($type !== VariantPriceType::Retail) {
                    return false;
                }

                if (strcasecmp((string) $price->currency, 'TZS') !== 0) {
                    return false;
                }

                if ($price->starts_at !== null && $price->starts_at->gt($now)) {
                    return false;
                }

                if ($price->ends_at !== null && $price->ends_at->lt($now)) {
                    return false;
                }

                return true;
            })
            ->sortBy('minimum_quantity')
            ->first();
    }

    private function mainInventoryRow(ProductVariant $variant): ?VariantInventory
    {
        $rows = $variant->relationLoaded('inventories')
            ? $variant->inventories
            : $variant->inventories()->get();

        return $rows->first(function (VariantInventory $row): bool {
            return strtoupper((string) $row->warehouse_code) === 'MAIN' && (bool) $row->is_active;
        }) ?? $rows->first(fn (VariantInventory $row) => strtoupper((string) $row->warehouse_code) === 'MAIN');
    }

    /**
     * @return array{product_id: string, success: true, message: string}
     */
    private function success(string $productId, string $message): array
    {
        return [
            'product_id' => $productId,
            'success' => true,
            'message' => $message,
        ];
    }

    /**
     * @return array{product_id: string, success: false, message: string}
     */
    private function failure(string $productId, string $message): array
    {
        return [
            'product_id' => $productId,
            'success' => false,
            'message' => $message,
        ];
    }

    private function firstValidationMessage(ValidationException $exception): string
    {
        $messages = $exception->errors();
        foreach ($messages as $fieldMessages) {
            if (is_array($fieldMessages) && isset($fieldMessages[0]) && is_string($fieldMessages[0])) {
                return $fieldMessages[0];
            }
        }

        return $exception->getMessage() ?: 'Validation failed.';
    }
}
