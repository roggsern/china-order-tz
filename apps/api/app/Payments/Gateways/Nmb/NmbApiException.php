<?php

namespace App\Payments\Gateways\Nmb;

use RuntimeException;

class NmbApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $gatewayResponse
     */
    public function __construct(
        string $message,
        private readonly bool $transient = false,
        private readonly ?int $statusCode = null,
        ?\Throwable $previous = null,
        private readonly array $gatewayResponse = [],
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isTransient(): bool
    {
        return $this->transient;
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function gatewayResponse(): array
    {
        return $this->gatewayResponse;
    }
}
