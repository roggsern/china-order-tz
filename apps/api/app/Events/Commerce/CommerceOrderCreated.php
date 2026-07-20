<?php

namespace App\Events\Commerce;

use App\Models\CommerceChannel;
use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommerceOrderCreated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $channelSnapshot
     */
    public function __construct(
        public readonly Order $order,
        public readonly CommerceChannel $channel,
        public readonly array $channelSnapshot,
    ) {}
}
