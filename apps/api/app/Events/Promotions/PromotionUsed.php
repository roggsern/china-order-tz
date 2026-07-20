<?php

namespace App\Events\Promotions;

use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PromotionUsed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Promotion $promotion,
        public readonly PromotionUsage $usage,
        public readonly Order $order,
    ) {}
}
