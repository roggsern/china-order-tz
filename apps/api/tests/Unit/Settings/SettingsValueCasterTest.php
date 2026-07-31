<?php

namespace Tests\Unit\Settings;

use App\Enums\SettingType;
use App\Services\Settings\SettingsValueCaster;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SettingsValueCasterTest extends TestCase
{
    private SettingsValueCaster $caster;

    protected function setUp(): void
    {
        parent::setUp();
        $this->caster = new SettingsValueCaster;
    }

    #[DataProvider('booleanProvider')]
    public function test_boolean_round_trip(mixed $input, bool $expected): void
    {
        $stored = $this->caster->toStorage($input, SettingType::Boolean);
        $this->assertSame($expected, $this->caster->fromStorage($stored, SettingType::Boolean));
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public static function booleanProvider(): array
    {
        return [
            'true bool' => [true, true],
            'false bool' => [false, false],
            'one' => [1, true],
            'zero' => [0, false],
            'string true' => ['true', true],
        ];
    }

    public function test_integer_and_string_and_json(): void
    {
        $this->assertSame(42, $this->caster->fromStorage(
            $this->caster->toStorage(42, SettingType::Integer),
            SettingType::Integer,
        ));

        $this->assertSame('nmb', $this->caster->fromStorage(
            $this->caster->toStorage('nmb', SettingType::String),
            SettingType::String,
        ));

        $json = ['a' => 1, 'b' => true];
        $this->assertSame($json, $this->caster->fromStorage(
            $this->caster->toStorage($json, SettingType::Json),
            SettingType::Json,
        ));
    }

    public function test_decimal_formats_two_places(): void
    {
        $stored = $this->caster->toStorage(12.5, SettingType::Decimal);
        $this->assertSame('12.50', $stored);
        $this->assertSame('12.50', $this->caster->fromStorage($stored, SettingType::Decimal));
    }
}
