<?php

namespace App\Payments\Gateways\Nmb;

class NmbCallbackVerifier
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $payload): bool
    {
        return false;
    }
}
