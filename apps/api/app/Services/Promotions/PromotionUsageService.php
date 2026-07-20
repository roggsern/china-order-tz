<?php

namespace App\Services\Promotions;

use App\Events\Promotions\PromotionUsed;
use App\Models\Order;
use App\Models\OrderDiscountSnapshot;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\User;
use App\Services\Promotions\DTOs\DiscountResolution;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PromotionUsageService
{
    public function totalUsageCount(Promotion $promotion): int
    {
        return PromotionUsage::query()->where('promotion_id', $promotion->id)->count();
    }

    public function customerUsageCount(Promotion $promotion, User $user): int
    {
        return PromotionUsage::query()
            ->where('promotion_id', $promotion->id)
            ->where('customer_id', $user->id)
            ->count();
    }

    public function paginateForPromotion(Promotion $promotion, int $perPage = 20): LengthAwarePaginator
    {
        return PromotionUsage::query()
            ->with(['customer:id,name,email', 'order:id,order_number,total,status'])
            ->where('promotion_id', $promotion->id)
            ->latest('used_at')
            ->paginate($perPage);
    }

    /**
     * Persist immutable order discount snapshots + usage rows after order creation.
     */
    public function recordForOrder(Order $order, DiscountResolution $resolution, ?User $customer = null): void
    {
        if ($resolution->discountTotal === '0.00' && $resolution->applications === []) {
            return;
        }

        DB::transaction(function () use ($order, $resolution, $customer) {
            foreach ($resolution->applications as $app) {
                OrderDiscountSnapshot::query()->create([
                    'order_id' => $order->id,
                    'order_item_id' => null,
                    'promotion_id' => $app['promotion_id'],
                    'promotion_name' => $app['promotion_name'],
                    'promotion_code' => $app['promotion_code'],
                    'original_amount' => $app['eligible_subtotal'],
                    'discount_amount' => $app['discount_amount'],
                    'final_amount' => bcsub($app['eligible_subtotal'], $app['discount_amount'], 2),
                    'currency' => $resolution->currency,
                ]);

                $usage = PromotionUsage::query()->create([
                    'promotion_id' => $app['promotion_id'],
                    'customer_id' => $customer?->id ?? $order->user_id,
                    'order_id' => $order->id,
                    'discount_amount' => $app['discount_amount'],
                    'currency' => $resolution->currency,
                    'used_at' => now(),
                ]);

                $promotion = Promotion::query()->find($app['promotion_id']);
                if ($promotion !== null) {
                    try {
                        event(new PromotionUsed($promotion, $usage, $order));
                    } catch (\Throwable $e) {
                        Log::warning('promotion.used_event_failed', [
                            'promotion_id' => $promotion->id,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            }
        });
    }
}
