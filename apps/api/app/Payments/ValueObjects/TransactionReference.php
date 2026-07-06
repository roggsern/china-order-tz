<?php

namespace App\Payments\ValueObjects;

use InvalidArgumentException;

final readonly class TransactionReference
{
    public function __construct(
        private string $value,
    ) {
        if (trim($value) === '') {
            throw new InvalidArgumentException('Transaction reference cannot be empty.');
        }
    }

    public static function fromNullable(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
