<?php

namespace App\Services\Analytics\DTOs;

use App\Models\Admin;
use App\Models\Store;
use App\Services\Reporting\DTOs\ReportPeriod;
use App\Services\Stores\ActiveStoreContext;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Resolved analytics query scope. Never owns business data — only filter inputs.
 */
final class AnalyticsFilter
{
    /**
     * @param  list<string>  $storeIds
     */
    public function __construct(
        public readonly ReportPeriod $period,
        public readonly array $storeIds,
        public readonly ?string $cashierId = null,
        public readonly ?string $customerId = null,
        public readonly ?string $categoryId = null,
        public readonly ?string $productId = null,
        public readonly ?string $paymentMethod = null,
        public readonly ?string $promotionId = null,
        public readonly ?string $returnReasonId = null,
        public readonly bool $posOnly = true,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromRequest(Admin $admin, array $input, ActiveStoreContext $stores): self
    {
        $period = ReportPeriod::fromInput(
            isset($input['from']) ? (string) $input['from'] : null,
            isset($input['to']) ? (string) $input['to'] : null,
            30,
        );

        $assigned = $stores->assignedStores($admin);
        $assignedIds = $assigned->pluck('id')->all();

        $storeId = filled($input['store_id'] ?? null) ? (string) $input['store_id'] : null;
        if ($storeId !== null) {
            $store = Store::query()->find($storeId);
            if ($store === null) {
                throw ValidationException::withMessages(['store_id' => ['Store not found.']]);
            }
            $stores->assertCanAccess($admin, $store);
            $storeIds = [$store->id];
        } else {
            if ($admin->is_super_admin) {
                $storeIds = $assignedIds;
            } else {
                if ($assignedIds === []) {
                    throw ValidationException::withMessages([
                        'store_id' => ['No store assignment found.'],
                    ]);
                }
                $storeIds = $assignedIds;
            }
        }

        $cashierId = filled($input['cashier_id'] ?? null) ? (string) $input['cashier_id'] : null;
        if ($admin->isStoreCashier() && ! $admin->is_super_admin) {
            $cashierId = $admin->id;
        }

        $posOnly = ! array_key_exists('pos_only', $input)
            ? true
            : filter_var($input['pos_only'], FILTER_VALIDATE_BOOLEAN);

        return new self(
            period: $period,
            storeIds: $storeIds,
            cashierId: $cashierId,
            customerId: filled($input['customer_id'] ?? null) ? (string) $input['customer_id'] : null,
            categoryId: filled($input['category_id'] ?? null) ? (string) $input['category_id'] : null,
            productId: filled($input['product_id'] ?? null) ? (string) $input['product_id'] : null,
            paymentMethod: filled($input['payment_method'] ?? null)
                ? strtoupper((string) $input['payment_method'])
                : null,
            promotionId: filled($input['promotion_id'] ?? null) ? (string) $input['promotion_id'] : null,
            returnReasonId: filled($input['return_reason_id'] ?? null) ? (string) $input['return_reason_id'] : null,
            posOnly: $posOnly,
        );
    }

    /**
     * @return Collection<int, string>
     */
    public function storeIdCollection(): Collection
    {
        return collect($this->storeIds);
    }

    public function hasStores(): bool
    {
        return $this->storeIds !== [];
    }

    /** Stable cache key for short-TTL analytics summaries. */
    public function cacheKey(string $section): string
    {
        $payload = [
            's' => $section,
            'from' => $this->period->from->toDateString(),
            'to' => $this->period->to->toDateString(),
            'stores' => $this->storeIds,
            'cashier' => $this->cashierId,
            'customer' => $this->customerId,
            'category' => $this->categoryId,
            'product' => $this->productId,
            'pay' => $this->paymentMethod,
            'promo' => $this->promotionId,
            'reason' => $this->returnReasonId,
            'pos' => $this->posOnly,
        ];

        return 'retail_analytics:'.hash('xxh3', json_encode($payload) ?: '');
    }
}
