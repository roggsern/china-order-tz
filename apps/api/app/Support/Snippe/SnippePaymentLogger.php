<?php

namespace App\Support\Snippe;

use Illuminate\Support\Facades\Log;

class SnippePaymentLogger
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
        return (string) config('payments.snippe.log_channel', 'stack');
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context): array
    {
        return array_merge(['domain' => 'snippe_payments'], $context);
    }
}
