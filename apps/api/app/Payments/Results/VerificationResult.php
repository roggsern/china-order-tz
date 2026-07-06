<?php

namespace App\Payments\Results;

class VerificationResult
{
    public function __construct(
        public readonly bool $verified,
        public readonly string $status,
        public readonly string $message,
    ) {}
}
