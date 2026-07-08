<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PaymentReferenceGenerator
{
    public function generate(): string
    {
        $prefix = (string) config('payments.reference_prefix', 'PAY');
        $padding = max(1, (int) config('payments.reference_sequence_padding', 6));
        $year = now()->format('Y');

        return DB::transaction(function () use ($prefix, $padding, $year): string {
            $latestReference = Payment::query()
                ->where('reference', 'like', "{$prefix}-{$year}-%")
                ->lockForUpdate()
                ->orderByDesc('reference')
                ->value('reference');

            $nextSequence = 1;

            if ($latestReference !== null) {
                $sequencePart = (string) str($latestReference)->afterLast('-');
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
