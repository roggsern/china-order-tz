<?php

namespace Tests\Unit\Payments\Snippe;

use App\Payments\Gateways\Snippe\SnippePhoneNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SnippePhoneNormalizerTest extends TestCase
{
    #[DataProvider('validPhoneProvider')]
    public function test_normalizes_tanzania_phone_formats(string $input, string $expected): void
    {
        $this->assertSame($expected, SnippePhoneNormalizer::normalize($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function validPhoneProvider(): array
    {
        return [
            'e164 plus' => ['+255712345678', '255712345678'],
            'e164 digits' => ['255712345678', '255712345678'],
            'leading zero' => ['0712345678', '255712345678'],
            'national nine digits' => ['712345678', '255712345678'],
            'airtel prefix' => ['0781000000', '255781000000'],
        ];
    }

    #[DataProvider('invalidPhoneProvider')]
    public function test_rejects_invalid_phone_numbers(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        SnippePhoneNormalizer::normalize($input);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidPhoneProvider(): array
    {
        return [
            'empty' => [''],
            'too short' => ['25571'],
            'invalid prefix' => ['255512345678'],
            'letters' => ['not-a-phone'],
        ];
    }

    public function test_masks_phone_for_logging(): void
    {
        $masked = SnippePhoneNormalizer::mask('255712345678');

        $this->assertStringNotContainsString('12345678', $masked);
        $this->assertStringStartsWith('25571', $masked);
        $this->assertStringEndsWith('678', $masked);
    }
}
