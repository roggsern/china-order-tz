<?php

namespace App\Services\Refunds;

use App\Models\RefundTransaction;
use App\Services\Refunds\Contracts\RefundProviderInterface;
use App\Services\Refunds\Providers\ManualRefundProvider;
use App\Services\Refunds\Providers\NmbRefundProviderPlaceholder;
use Illuminate\Support\Str;

class RefundProviderRegistry
{
    /** @var array<string, RefundProviderInterface> */
    private array $providers;

    public function __construct()
    {
        $this->providers = [
            (new ManualRefundProvider())->code() => new ManualRefundProvider(),
            (new NmbRefundProviderPlaceholder())->code() => new NmbRefundProviderPlaceholder(),
        ];
    }

    public function resolve(RefundTransaction $refund): RefundProviderInterface
    {
        $method = Str::lower((string) ($refund->method ?? 'manual'));

        if (str_contains($method, 'nmb')) {
            return $this->providers['nmb'];
        }

        return $this->providers['manual'];
    }
}
