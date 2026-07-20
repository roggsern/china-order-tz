<?php

namespace App\Events\Promotions;

use App\Models\Admin;
use App\Models\Promotion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PromotionCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Promotion $promotion,
        public readonly ?Admin $admin = null,
    ) {}
}
