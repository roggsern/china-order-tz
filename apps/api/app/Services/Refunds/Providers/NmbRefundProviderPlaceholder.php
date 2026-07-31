<?php

namespace App\Services\Refunds\Providers;

use App\Models\Admin;
use App\Models\RefundTransaction;
use App\Services\Refunds\Contracts\RefundProviderInterface;
use App\Services\Refunds\RefundProviderResult;

/**
 * Placeholder for future NMB PSP refund execution — not wired to PaymentOrchestrator.
 */
class NmbRefundProviderPlaceholder implements RefundProviderInterface
{
    public function code(): string
    {
        return 'nmb';
    }

    public function isAvailable(RefundTransaction $refund): bool
    {
        return false;
    }

    public function process(RefundTransaction $refund, Admin $admin): RefundProviderResult
    {
        return RefundProviderResult::unavailable('NMB refund provider is not enabled yet.');
    }
}
