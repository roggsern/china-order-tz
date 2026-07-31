<?php

namespace App\Services\Stores;

use App\Models\Store;

/**
 * Resolves structured store business settings from stores.settings JSON.
 * Does not invent a second settings store; preserves unknown keys (e.g. legacy receipt fields).
 */
final class StoreSettingsResolver
{
    /** @var list<string> */
    public const SECTIONS = ['business', 'receipt', 'customer', 'social'];

    /**
     * @var array<string, list<string>>
     */
    public const SECTION_KEYS = [
        'business' => ['display_name', 'phone', 'email', 'address'],
        'receipt' => ['footer_message', 'show_logo'],
        'customer' => ['support_phone', 'support_email'],
        'social' => ['instagram', 'facebook', 'tiktok'],
    ];

    /**
     * @return array{
     *   business: array<string, mixed>,
     *   receipt: array<string, mixed>,
     *   customer: array<string, mixed>,
     *   social: array<string, mixed>
     * }
     */
    public function defaults(): array
    {
        return [
            'business' => [
                'display_name' => '',
                'phone' => '',
                'email' => '',
                'address' => '',
            ],
            'receipt' => [
                'footer_message' => '',
                'show_logo' => true,
            ],
            'customer' => [
                'support_phone' => '',
                'support_email' => '',
            ],
            'social' => [
                'instagram' => '',
                'facebook' => '',
                'tiktok' => '',
            ],
        ];
    }

    /**
     * @return array{
     *   store_id: string,
     *   store_code: string,
     *   store_name: string,
     *   business: array<string, mixed>,
     *   receipt: array<string, mixed>,
     *   customer: array<string, mixed>,
     *   social: array<string, mixed>
     * }
     */
    public function resolve(Store $store): array
    {
        $sections = $this->resolveSections($store);

        return [
            'store_id' => $store->id,
            'store_code' => $store->code,
            'store_name' => $store->name,
            ...$sections,
        ];
    }

    /**
     * @return array{
     *   business: array<string, mixed>,
     *   receipt: array<string, mixed>,
     *   customer: array<string, mixed>,
     *   social: array<string, mixed>
     * }
     */
    public function resolveSections(Store $store): array
    {
        $settings = is_array($store->settings) ? $store->settings : [];
        $defaults = $this->defaults();
        $resolved = [];

        foreach (self::SECTIONS as $section) {
            $raw = is_array($settings[$section] ?? null) ? $settings[$section] : [];
            $resolved[$section] = $this->normalizeSection($section, $raw, $defaults[$section]);
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public function normalizeSection(string $section, array $raw, array $defaults): array
    {
        $normalized = $defaults;

        foreach (self::SECTION_KEYS[$section] ?? [] as $key) {
            if (! array_key_exists($key, $raw)) {
                continue;
            }

            $normalized[$key] = $section === 'receipt' && $key === 'show_logo'
                ? (bool) $raw[$key]
                : $this->stringValue($raw[$key]);
        }

        return $normalized;
    }

    /**
     * Snapshot of managed sections only (for audit before/after).
     *
     * @return array<string, mixed>
     */
    public function snapshot(Store $store): array
    {
        return $this->resolveSections($store);
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }
}
