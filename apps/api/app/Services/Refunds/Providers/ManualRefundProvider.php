<?php

namespace App\Services\Refunds\Providers;

use App\Models\Admin;
use App\Models\RefundTransaction;
use App\Services\Refunds\Contracts\RefundProviderInterface;
use App\Services\Refunds\RefundProviderResult;

/**
 * Manual refund provider — finance confirms payout outside PSP rails.
 */
class ManualRefundProvider implements RefundProviderInterface
{
    public function code(): string
    {
        return 'manual';
    }

    public function isAvailable(RefundTransaction $refund): bool
    {
        return true;
    }

    public function process(RefundTransaction $refund, Admin $admin): RefundProviderResult
    {
        $reference = $refund->reference ?: 'MANUAL-'.strtoupper(substr($refund->id, 0, 8));

        return RefundProviderResult::succeeded($reference, [
            'provider' => $this->code(),
            'processed_by_admin_id' => $admin->id,
            'mode' => 'manual_confirmation',
        ]);
    }
}
