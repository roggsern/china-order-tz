<?php

namespace App\Support\Nmb;

use Illuminate\Support\Facades\Log;

class NmbPaymentLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $event, array $context = []): void
    {
        Log::channel($this->channel())->info($event, $this->normalizeContext($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $event, array $context = []): void
    {
        Log::channel($this->channel())->warning($event, $this->normalizeContext($context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $event, array $context = []): void
    {
        Log::channel($this->channel())->error($event, $this->normalizeContext($context));
    }

    private function channel(): string
    {
        return (string) config('services.nmb.logging.channel', 'stack');
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context): array
    {
        return array_merge(['domain' => 'nmb_payments'], $context);
    }
}
