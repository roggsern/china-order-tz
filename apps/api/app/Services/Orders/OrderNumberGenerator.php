<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderNumberGenerator
{
    public function generate(): string
    {
        $prefix = (string) config('orders.number_prefix', 'COTZ');
        $padding = max(1, (int) config('orders.number_sequence_padding', 6));
        $year = now()->format('Y');

        return DB::transaction(function () use ($prefix, $padding, $year): string {
            $latestOrderNumber = Order::query()
                ->where('order_number', 'like', "{$prefix}-{$year}-%")
                ->lockForUpdate()
                ->orderByDesc('order_number')
                ->value('order_number');

            $nextSequence = 1;

            if ($latestOrderNumber !== null) {
                $sequencePart = (string) str($latestOrderNumber)->afterLast('-');
                $nextSequence = ((int) $sequencePart) + 1;
            }

            return sprintf(
                '%s-%s-%s',
                $prefix,
                $year,
                str_pad((string) $nextSequence, $padding, '0', STR_PAD_LEFT),
            );
        });
    }
}
