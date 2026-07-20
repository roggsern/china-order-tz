<?php

namespace App\Services\Loyalty;

use App\Models\LoyaltyAccount;
use Illuminate\Support\Facades\DB;

class LoyaltyNumberGenerator
{
    public function generate(): string
    {
        return DB::transaction(function () {
            $year = now()->format('Y');
            $prefix = "LY-{$year}-";
            $latest = LoyaltyAccount::query()
                ->where('loyalty_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('loyalty_number')
                ->value('loyalty_number');

            $next = 1;
            if ($latest) {
                $next = ((int) (string) str($latest)->afterLast('-')) + 1;
            }

            return $prefix.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        });
    }
}
