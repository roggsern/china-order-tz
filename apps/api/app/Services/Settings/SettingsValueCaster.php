<?php

namespace App\Services\Settings;

use App\Enums\SettingType;
use InvalidArgumentException;

final class SettingsValueCaster
{
    public function toStorage(mixed $value, SettingType $type): string
    {
        return match ($type) {
            SettingType::String => (string) $value,
            SettingType::Integer => (string) (int) $value,
            SettingType::Decimal => $this->formatDecimal($value),
            SettingType::Boolean => $this->normalizeBoolean($value) ? '1' : '0',
            SettingType::Json => $this->encodeJson($value),
        };
    }

    public function fromStorage(?string $value, SettingType $type): mixed
    {
        if ($value === null) {
            return match ($type) {
                SettingType::Boolean => false,
                SettingType::Integer => 0,
                SettingType::Decimal => '0.00',
                SettingType::Json => null,
                SettingType::String => null,
            };
        }

        return match ($type) {
            SettingType::String => $value,
            SettingType::Integer => (int) $value,
            SettingType::Decimal => $value,
            SettingType::Boolean => in_array($value, ['1', 'true', 'on', 'yes'], true),
            SettingType::Json => json_decode($value, true),
        };
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
    }

    private function formatDecimal(mixed $value): string
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Decimal setting value must be numeric.');
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function encodeJson(mixed $value): string
    {
        if (is_string($value)) {
            json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $value;
            }
        }

        $encoded = json_encode($value, JSON_THROW_ON_ERROR);

        return $encoded;
    }
}
