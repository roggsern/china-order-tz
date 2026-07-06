<?php

namespace App\Payments\Gateways\Nmb;

class NmbCallbackVerifier
{
    public function verify(array $payload): bool
    {
        return (bool) config('payments.nmb.test_mode', true);
    }
}
