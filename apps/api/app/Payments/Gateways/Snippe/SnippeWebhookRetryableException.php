<?php

namespace App\Payments\Gateways\Snippe;

use Symfony\Component\HttpKernel\Exception\HttpException;

class SnippeWebhookRetryableException extends HttpException
{
    public static function verificationUnavailable(string $message = 'Snippe verification temporarily unavailable.'): self
    {
        return new self(503, $message);
    }

    public static function transactionNotMatched(string $message = 'Snippe webhook transaction not matched.'): self
    {
        return new self(503, $message);
    }
}
