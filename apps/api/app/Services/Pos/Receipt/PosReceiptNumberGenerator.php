<?php

namespace App\Services\Pos\Receipt;

use App\Models\PosReceipt;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * Enterprise receipt numbering: {STORE_CODE}-{YEAR}-{SEQ}.
 * Example: ZION-2026-000001
 *
 * Store-scoped yearly sequences keep numbers unique, searchable, and audit-friendly
 * without coupling to UUID fragments.
 */
class PosReceiptNumberGenerator
{
    public function generate(Store $store): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $store->code) ?: 'POS');
        $year = now()->format('Y');
        $padding = 6;

        return DB::transaction(function () use ($prefix, $year, $padding): string {
            $pattern = "{$prefix}-{$year}-%";

            $latest = PosReceipt::query()
                ->where('receipt_number', 'like', $pattern)
                ->lockForUpdate()
                ->orderByDesc('receipt_number')
                ->value('receipt_number');

            $next = 1;
            if ($latest !== null) {
                $seq = (string) str($latest)->afterLast('-');
                $next = ((int) $seq) + 1;
            }

            return sprintf(
                '%s-%s-%s',
                $prefix,
                $year,
                str_pad((string) $next, $padding, '0', STR_PAD_LEFT),
            );
        });
    }
}
