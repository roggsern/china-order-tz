<?php

namespace App\Events\Promotions;

use App\Models\Admin;
use App\Models\Promotion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PromotionUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $before
     */
    public function __construct(
        public readonly Promotion $promotion,
        public readonly array $before,
        public readonly ?Admin $admin = null,
    ) {}
}
