<?php

namespace App\Events\Commerce;

use App\Models\CommerceChannel;
use App\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommerceChannelAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Product $product,
        public readonly CommerceChannel $channel,
        public readonly ?string $actorType = null,
        public readonly ?string $actorId = null,
    ) {}
}
