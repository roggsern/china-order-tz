<?php

namespace App\Services\AdminProducts;

use App\Actions\Concerns\GuardsActiveProductSubResourceIntegrity;
use App\Enums\VariantPriceType;
use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantInventory;
use App\Models\VariantPrice;
use App\Services\China\Procurement\ChinaCommercialStockService;
use App\Services\Commerce\CommerceChannelResolver;
use App\Services\Inventory\AdminInventoryApplicationService;
use App\Services\Inventory\TzLocalInventoryScope;
use App\Services\ProductPurchasability\ProductPurchasabilityPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Selective variant bulk operations — reuses variant_prices, china_commercial_stocks, and lifecycle guards.
 */
final class VariantBulkActionService
{
    use GuardsActiveProductSubResourceIntegrity;

    public const ACTION_SET_SELLING_PRICE = 'set_selling_price';

    public const ACTION_SET_COST_PRICE = 'set_cost_price';

    public const ACTION_SET_COMMERCIAL_STOCK = 'set_commercial_stock';

    public const ACTION_SET_INVENTORY_STOCK = 'set_inventory_stock';

    public const ACTION_ACTIVATE = 'activate';

    public const ACTION_DEACTIVATE = 'deactivate';

    /** @var list<string> */
    public const ACTIONS = [
        self::ACTION_SET_SELLING_PRICE,
        self::ACTION_SET_COST_PRICE,
        self::ACTION_SET_COMMERCIAL_STOCK,
        self::ACTION_SET_INVENTORY_STOCK,
        self::ACTION_ACTIVATE,
        self::ACTION_DEACTIVATE,
    ];

    public function __construct(
        private readonly ProductPurchasabilityPolicy $purchasabilityPolicy,
        private readonly ClearSimpleProductCommerceOnVariantPathActivation $simpleCommerceCleaner,
        private readonly ChinaCommercialStockService $commercialStock,
        private readonly CommerceChannelResolver $commerceChannels,
        private readonly AdminInventoryApplicationService $inventory,
        private readonly TzLocalInventoryScope $tzLocalScope,
    ) {}

    /**
     * @param  list<string>  $variantIds
     * @param  array{
     *   amount?: float|int|null,
     *   cost_price?: float|int|null,
     *   available_quantity?: int|null,
     *   on_hand?: int|null,
     *   quantity?: int|null,
     *   warehouse_code?: string|null,
     *   reserved?: int|null,
     *   reorder_level?: int|null,
     *   safety_stock?: int|null,
     *   is_active?: bool|null
     * }  $payload
     * @return array{
     *   batch_id: string,
     *   action_key: string,
     *   product_id: string,
     *   total: int,
     *   succeeded: int,
     *   failed: int,
     *   results: list<array{variant_id: string, success: bool, message: string}>
     * }
     */
    public function execute(
        Admin $admin,
        Product $product,
        string $actionKey,
        array $variantIds,
        array $payload = [],
    ): array {
        $actionKey = strtolower(trim($actionKey));
        $variantIds = array_values(array_unique(array_filter(array_map(
            static fn ($id) => is_string($id) ? trim($id) : '',
            $variantIds,
        ))));

        if ($variantIds === []) {
            throw ValidationException::withMessages([
                'variant_ids' => ['At least one variant id is required.'],
            ]);
        }

        if (! in_array($actionKey, self::ACTIONS, true)) {
            throw ValidationException::withMessages([
                'action_key' => ['Unsupported bulk action.'],
            ]);
        }

        $this->validatePayload($actionKey, $payload);

        $product->loadMissing([
            'commerceChannel',
            'variants.prices',
            'variants.inventories',
        ]);

        $variantsById = $product->variants->keyBy('id');
        $batchId = (string) Str::uuid();
        $results = [];

        foreach ($variantIds as $variantId) {
            $variant = $variantsById->get($variantId);

            if ($variant === null) {
                $results[] = $this->failure($variantId, 'Variant not found or does not belong to this product.');
                continue;
            }

            try {
                $message = $this->applyAction($admin, $product, $variant, $actionKey, $payload);
                $results[] = $this->success($variantId, $message);
            } catch (ValidationException $exception) {
                $results[] = $this->failure($variantId, $this->firstValidationMessage($exception));
            } catch (Throwable $exception) {
                $results[] = $this->failure($variantId, $exception->getMessage() ?: 'Bulk action failed.');
            }
        }

        $succeeded = count(array_filter($results, static fn (array $row): bool => $row['success']));
        $failed = count($results) - $succeeded;

        return [
            'batch_id' => $batchId,
            'action_key' => $actionKey,
            'product_id' => $product->id,
            'total' => count($results),
            'succeeded' => $succeeded,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * @param  array{
     *   amount?: float|int|null,
     *   cost_price?: float|int|null,
     *   available_quantity?: int|null,
     *   on_hand?: int|null,
     *   quantity?: int|null,
     *   warehouse_code?: string|null,
     *   reserved?: int|null,
     *   reorder_level?: int|null,
     *   safety_stock?: int|null,
     *   is_active?: bool|null
     * }  $payload
     */
    private function applyAction(
        Admin $admin,
        Product $product,
        ProductVariant $variant,
        string $actionKey,
        array $payload,
    ): string {
        return match ($actionKey) {
            self::ACTION_SET_SELLING_PRICE => $this->applySellingPrice($product, $variant, (float) $payload['amount']),
            self::ACTION_SET_COST_PRICE => $this->applyCostPrice($product, $variant, (float) $payload['cost_price']),
            self::ACTION_SET_COMMERCIAL_STOCK => $this->applyCommercialStock(
                $product,
                $variant,
                (int) $payload['available_quantity'],
            ),
            self::ACTION_SET_INVENTORY_STOCK => $this->applyInventoryStock($admin, $product, $variant, $payload),
            self::ACTION_ACTIVATE => $this->applyActivation($product, $variant, true),
            self::ACTION_DEACTIVATE => $this->applyActivation($product, $variant, false),
            default => throw ValidationException::withMessages([
                'action_key' => ['Unsupported bulk action.'],
            ]),
        };
    }

    private function applySellingPrice(Product $product, ProductVariant $variant, float $amount): string
    {
        $hadSellableVariants = $this->snapshotSellableVariants($this->purchasabilityPolicy, $product);

        DB::transaction(function () use ($product, $variant, $amount, $hadSellableVariants): void {
            $this->upsertRetailPrice($variant, $amount, null);
            $this->afterVariantPurchasabilityMutation(
                $this->purchasabilityPolicy,
                $this->simpleCommerceCleaner,
                $product,
                $hadSellableVariants,
            );
        });

        return 'Selling price updated.';
    }

    private function applyCostPrice(Product $product, ProductVariant $variant, float $costPrice): string
    {
        $hadSellableVariants = $this->snapshotSellableVariants($this->purchasabilityPolicy, $product);

        DB::transaction(function () use ($product, $variant, $costPrice, $hadSellableVariants): void {
            $this->upsertRetailPrice($variant, null, $costPrice);
            $this->afterVariantPurchasabilityMutation(
                $this->purchasabilityPolicy,
                $this->simpleCommerceCleaner,
                $product,
                $hadSellableVariants,
            );
        });

        return 'Cost price updated.';
    }

    private function applyCommercialStock(Product $product, ProductVariant $variant, int $availableQuantity): string
    {
        if (! $this->commerceChannels->isChinaImportProduct($product)) {
            throw ValidationException::withMessages([
                'commerce_channel' => ['Commercial stock is only available for CHINA_IMPORT products.'],
            ]);
        }

        $hadSellableVariants = $this->snapshotSellableVariants($this->purchasabilityPolicy, $product);

        DB::transaction(function () use ($product, $variant, $availableQuantity, $hadSellableVariants): void {
            $this->commercialStock->setAvailable($product, $availableQuantity, $variant);
            $this->afterVariantPurchasabilityMutation(
                $this->purchasabilityPolicy,
                $this->simpleCommerceCleaner,
                $product,
                $hadSellableVariants,
            );
        });

        return 'Commercial stock updated.';
    }

    /**
     * @param  array{
     *   on_hand?: int|null,
     *   quantity?: int|null,
     *   warehouse_code?: string|null,
     *   reserved?: int|null,
     *   reorder_level?: int|null,
     *   safety_stock?: int|null,
     *   is_active?: bool|null
     * }  $payload
     */
    private function applyInventoryStock(
        Admin $admin,
        Product $product,
        ProductVariant $variant,
        array $payload,
    ): string {
        if ($this->commerceChannels->isChinaImportProduct($product)) {
            throw ValidationException::withMessages([
                'commerce_channel' => ['Warehouse inventory is not available for CHINA_IMPORT products.'],
            ]);
        }

        $onHand = array_key_exists('on_hand', $payload)
            ? (int) $payload['on_hand']
            : (int) ($payload['quantity'] ?? 0);

        if ($onHand < 0) {
            throw ValidationException::withMessages([
                'payload.on_hand' => ['Warehouse stock must be zero or greater.'],
            ]);
        }

        $inventoryData = [
            'on_hand' => $onHand,
        ];

        $defaults = $this->tzLocalScope->applyVariantInventoryDefaults($product, [
            'warehouse_code' => strtoupper(trim((string) ($payload['warehouse_code'] ?? 'MAIN'))),
        ]);
        $inventoryData['warehouse_code'] = $defaults['warehouse_code'];
        if (array_key_exists('inventory_location_id', $defaults)) {
            $inventoryData['inventory_location_id'] = $defaults['inventory_location_id'];
        }

        if (array_key_exists('reserved', $payload)) {
            $inventoryData['reserved'] = max(0, (int) $payload['reserved']);
        }

        if (array_key_exists('reorder_level', $payload)) {
            $inventoryData['reorder_level'] = max(0, (int) $payload['reorder_level']);
        }

        if (array_key_exists('safety_stock', $payload)) {
            $inventoryData['safety_stock'] = max(0, (int) $payload['safety_stock']);
        }

        if (array_key_exists('is_active', $payload)) {
            $inventoryData['is_active'] = (bool) $payload['is_active'];
        }

        if (array_key_exists('reserved', $inventoryData) && $inventoryData['reserved'] > $onHand) {
            throw ValidationException::withMessages([
                'payload.reserved' => ['Reserved cannot exceed on hand.'],
            ]);
        }

        $hadSellableVariants = $this->snapshotSellableVariants($this->purchasabilityPolicy, $product);
        $row = $this->inventoryRow($variant, (string) $inventoryData['warehouse_code']);

        DB::transaction(function () use ($admin, $product, $variant, $inventoryData, $row, $hadSellableVariants): void {
            if ($row === null) {
                $this->inventory->createVariantInventory($variant, $inventoryData, $admin);
            } else {
                $this->inventory->updateVariantInventory($row, $inventoryData, $admin);
            }

            $this->afterVariantPurchasabilityMutation(
                $this->purchasabilityPolicy,
                $this->simpleCommerceCleaner,
                $product,
                $hadSellableVariants,
            );
        });

        return 'Warehouse stock updated.';
    }

    private function inventoryRow(ProductVariant $variant, string $warehouseCode): ?VariantInventory
    {
        $rows = $variant->relationLoaded('inventories')
            ? $variant->inventories
            : $variant->inventories()->get();

        $code = strtoupper($warehouseCode);

        return $rows->first(function (VariantInventory $row) use ($code): bool {
            return strtoupper((string) $row->warehouse_code) === $code && (bool) $row->is_active;
        }) ?? $rows->first(fn (VariantInventory $row) => strtoupper((string) $row->warehouse_code) === $code);
    }

    private function applyActivation(Product $product, ProductVariant $variant, bool $active): string
    {
        $hadSellableVariants = $this->snapshotSellableVariants($this->purchasabilityPolicy, $product);

        DB::transaction(function () use ($product, $variant, $active, $hadSellableVariants): void {
            $variant->forceFill(['is_active' => $active])->save();
            $this->afterVariantPurchasabilityMutation(
                $this->purchasabilityPolicy,
                $this->simpleCommerceCleaner,
                $product,
                $hadSellableVariants,
            );
        });

        return $active ? 'Variant activated.' : 'Variant deactivated.';
    }

    private function upsertRetailPrice(ProductVariant $variant, ?float $amount, ?float $costPrice): void
    {
        $retail = $this->activeRetailPrice($variant);

        if ($retail !== null) {
            $updates = ['is_active' => true];

            if ($amount !== null) {
                $updates['amount'] = number_format($amount, 2, '.', '');
            }

            if ($costPrice !== null) {
                $updates['cost_price'] = number_format($costPrice, 2, '.', '');
            }

            $retail->forceFill($updates)->save();

            return;
        }

        VariantPrice::query()->create([
            'product_variant_id' => $variant->id,
            'price_type' => VariantPriceType::Retail,
            'currency' => 'TZS',
            'amount' => number_format($amount ?? 0, 2, '.', ''),
            'cost_price' => $costPrice !== null ? number_format($costPrice, 2, '.', '') : null,
            'minimum_quantity' => 1,
            'is_active' => true,
        ]);
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

    /**
     * @param  array{
     *   amount?: float|int|null,
     *   cost_price?: float|int|null,
     *   available_quantity?: int|null,
     *   on_hand?: int|null,
     *   quantity?: int|null,
     *   warehouse_code?: string|null,
     *   reserved?: int|null,
     *   reorder_level?: int|null,
     *   safety_stock?: int|null,
     *   is_active?: bool|null
     * }  $payload
     */
    private function validatePayload(string $actionKey, array $payload): void
    {
        match ($actionKey) {
            self::ACTION_SET_SELLING_PRICE => $this->requireNumeric($payload, 'amount', 'Selling price'),
            self::ACTION_SET_COST_PRICE => $this->requireNumeric($payload, 'cost_price', 'Cost price'),
            self::ACTION_SET_COMMERCIAL_STOCK => $this->requireInteger($payload, 'available_quantity', 'Commercial stock'),
            self::ACTION_SET_INVENTORY_STOCK => $this->validateInventoryPayload($payload),
            self::ACTION_ACTIVATE, self::ACTION_DEACTIVATE => null,
            default => throw ValidationException::withMessages([
                'action_key' => ['Unsupported bulk action.'],
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validateInventoryPayload(array $payload): void
    {
        if (! array_key_exists('on_hand', $payload) && ! array_key_exists('quantity', $payload)) {
            throw ValidationException::withMessages([
                'payload.on_hand' => ['Warehouse stock is required.'],
            ]);
        }

        $key = array_key_exists('on_hand', $payload) ? 'on_hand' : 'quantity';
        $this->requireInteger($payload, $key, 'Warehouse stock');

        foreach (['reserved', 'reorder_level', 'safety_stock'] as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            if (! is_numeric($payload[$field]) || (int) $payload[$field] < 0) {
                throw ValidationException::withMessages([
                    "payload.{$field}" => [ucfirst(str_replace('_', ' ', $field)).' must be zero or greater.'],
                ]);
            }
        }

        $onHand = (int) ($payload['on_hand'] ?? $payload['quantity']);
        if (array_key_exists('reserved', $payload) && (int) $payload['reserved'] > $onHand) {
            throw ValidationException::withMessages([
                'payload.reserved' => ['Reserved cannot exceed on hand.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requireNumeric(array $payload, string $key, string $label): void
    {
        if (! array_key_exists($key, $payload) || ! is_numeric($payload[$key])) {
            throw ValidationException::withMessages([
                "payload.{$key}" => ["{$label} is required."],
            ]);
        }

        if ((float) $payload[$key] < 0) {
            throw ValidationException::withMessages([
                "payload.{$key}" => ["{$label} must be zero or greater."],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requireInteger(array $payload, string $key, string $label): void
    {
        if (! array_key_exists($key, $payload) || ! is_numeric($payload[$key])) {
            throw ValidationException::withMessages([
                "payload.{$key}" => ["{$label} is required."],
            ]);
        }

        if ((int) $payload[$key] < 0) {
            throw ValidationException::withMessages([
                "payload.{$key}" => ["{$label} must be zero or greater."],
            ]);
        }
    }

    /**
     * @return array{variant_id: string, success: true, message: string}
     */
    private function success(string $variantId, string $message): array
    {
        return [
            'variant_id' => $variantId,
            'success' => true,
            'message' => $message,
        ];
    }

    /**
     * @return array{variant_id: string, success: false, message: string}
     */
    private function failure(string $variantId, string $message): array
    {
        return [
            'variant_id' => $variantId,
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
