<?php

namespace App\Payments\Exceptions;

use App\Enums\PaymentMethod;
use RuntimeException;

class PaymentGatewayNotFoundException extends RuntimeException
{
    public static function forMethod(PaymentMethod $method): self
    {
        return new self(
            "No payment gateway is registered for method [{$method->value}].",
        );
    }
}
