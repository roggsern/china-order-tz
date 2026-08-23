<?php

namespace Tests\Unit\Payments\Snippe;

use App\Payments\Gateways\Snippe\SnippeAmountValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SnippeAmountValidatorTest extends TestCase
{
    public function test_accepts_whole_tzs_amount_at_minimum(): void
    {
        $result = SnippeAmountValidator::assertCollectible('500.00', 'TZS');

        $this->assertSame(500, $result['integer_amount']);
        $this->assertSame('TZS', $result['currency']);
    }

    public function test_rejects_non_tzs_currency(): void
    {
        $this->expectException(ValidationException::class);

        SnippeAmountValidator::assertCollectible('1000.00', 'USD');
    }

    public function test_rejects_amount_below_minimum(): void
    {
        $this->expectException(ValidationException::class);

        SnippeAmountValidator::assertCollectible('499.00', 'TZS');
    }

    public function test_rejects_fractional_tzs_amount(): void
    {
        $this->expectException(ValidationException::class);

        SnippeAmountValidator::assertCollectible('1000.50', 'TZS');
    }

    public function test_fractional_policy_is_explicit(): void
    {
        $this->assertFalse(SnippeAmountValidator::isWholeTzsAmount('45000.25'));
        $this->assertTrue(SnippeAmountValidator::isWholeTzsAmount('45000.00'));
        $this->assertTrue(SnippeAmountValidator::isWholeTzsAmount('45000'));
    }
}
