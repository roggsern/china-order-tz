<?php

namespace App\Services\Refunds;

final class RefundProviderResult
{
    /**
     * @param  array<string, mixed>|null  $providerResponse
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $providerReference = null,
        public readonly ?array $providerResponse = null,
        public readonly ?string $failureReason = null,
    ) {}

    public static function succeeded(?string $reference = null, ?array $response = null): self
    {
        return new self(true, $reference, $response);
    }

    public static function failed(string $reason, ?array $response = null): self
    {
        return new self(false, null, $response, $reason);
    }

    public static function unavailable(string $reason): self
    {
        return new self(false, null, ['unavailable' => true], $reason);
    }
}
