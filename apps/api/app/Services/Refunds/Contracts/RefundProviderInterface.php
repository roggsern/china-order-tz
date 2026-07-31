<?php

namespace App\Services\Refunds\Contracts;

use App\Models\Admin;
use App\Models\RefundTransaction;
use App\Services\Refunds\RefundProviderResult;

interface RefundProviderInterface
{
    public function code(): string;

    public function isAvailable(RefundTransaction $refund): bool;

    public function process(RefundTransaction $refund, Admin $admin): RefundProviderResult;
}
