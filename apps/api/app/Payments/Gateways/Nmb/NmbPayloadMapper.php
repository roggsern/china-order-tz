<?php

namespace App\Payments\Gateways\Nmb;

class NmbPayloadMapper
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function isSuccessfulSimulation(array $payload): bool
    {
        return ($payload['result'] ?? null) === 'success';
    }
}
