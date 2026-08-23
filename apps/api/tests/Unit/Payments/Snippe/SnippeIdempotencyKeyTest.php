<?php

namespace Tests\Unit\Payments\Snippe;

use App\Payments\Gateways\Snippe\SnippeIdempotencyKey;
use Tests\TestCase;

class SnippeIdempotencyKeyTest extends TestCase
{
    public function test_idempotency_key_is_at_most_30_characters(): void
    {
        $key = SnippeIdempotencyKey::forPaymentTransaction('550e8400-e29b-41d4-a716-446655440000');

        $this->assertLessThanOrEqual(30, strlen($key));
        $this->assertSame(30, strlen($key));
    }

    public function test_same_transaction_produces_same_idempotency_key(): void
    {
        $transactionId = '550e8400-e29b-41d4-a716-446655440000';

        $this->assertSame(
            SnippeIdempotencyKey::forPaymentTransaction($transactionId),
            SnippeIdempotencyKey::forPaymentTransaction($transactionId),
        );
    }

    public function test_different_transactions_produce_different_idempotency_keys(): void
    {
        $first = SnippeIdempotencyKey::forPaymentTransaction('550e8400-e29b-41d4-a716-446655440000');
        $second = SnippeIdempotencyKey::forPaymentTransaction('660e8400-e29b-41d4-a716-446655440001');

        $this->assertNotSame($first, $second);
    }
}
