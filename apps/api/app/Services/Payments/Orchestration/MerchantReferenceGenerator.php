<?php

namespace App\Services\Payments\Orchestration;

use App\Models\PaymentTransaction;
use Illuminate\Support\Str;

class MerchantReferenceGenerator
{
    public function generate(): string
    {
        $prefix = (string) config('payments.orchestrator.merchant_reference_prefix', 'COTZ-PAY');
        $padding = max(1, (int) config('payments.orchestrator.merchant_reference_padding', 6));
        $date = now()->format('Ymd');
        $pattern = "{$prefix}-{$date}-%";

        $latest = PaymentTransaction::query()
            ->where('merchant_reference', 'like', $pattern)
            ->orderByDesc('merchant_reference')
            ->value('merchant_reference');

        $next = 1;
        if ($latest !== null) {
            $sequence = (string) str($latest)->afterLast('-');
            $next = ((int) $sequence) + 1;
        }

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $candidate = sprintf(
                '%s-%s-%s',
                $prefix,
                $date,
                str_pad((string) ($next + $attempt), $padding, '0', STR_PAD_LEFT),
            );

            $exists = PaymentTransaction::query()
                ->where('merchant_reference', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        return sprintf(
            '%s-%s-%s',
            $prefix,
            $date,
            str_pad((string) $next, $padding, '0', STR_PAD_LEFT),
        ).'-'.Str::upper(Str::random(4));
    }
}
