<?php

namespace App\Services\Pos\Receipt;

use App\Models\Store;

/**
 * Resolves configurable receipt branding from Store.settings (no code changes for content).
 *
 * Supported settings keys (under store.settings.receipt or flat):
 * - address, phone, tax_number, website, social_media
 * - footer_message, thank_you_message
 * - return_policy, exchange_policy
 */
class StoreReceiptSettings
{
    /**
     * @return array{
     *   address: string|null,
     *   phone: string|null,
     *   tax_number: string|null,
     *   website: string|null,
     *   social_media: string|null,
     *   footer_message: string|null,
     *   thank_you_message: string,
     *   return_policy: string|null,
     *   exchange_policy: string|null,
     *   logo_path: string|null,
     *   theme_color: string|null,
     *   store_name: string,
     *   store_code: string
     * }
     */
    public function forStore(Store $store): array
    {
        $settings = is_array($store->settings) ? $store->settings : [];
        $receipt = is_array($settings['receipt'] ?? null) ? $settings['receipt'] : [];

        $get = function (string $key, mixed $default = null) use ($receipt, $settings) {
            return $receipt[$key] ?? $settings[$key] ?? $default;
        };

        return [
            'address' => $this->nullableString($get('address')),
            'phone' => $this->nullableString($get('phone')),
            'tax_number' => $this->nullableString($get('tax_number')),
            'website' => $this->nullableString($get('website')),
            'social_media' => $this->nullableString($get('social_media')),
            'footer_message' => $this->nullableString($get('footer_message')),
            'thank_you_message' => (string) ($get('thank_you_message') ?: 'Thank you for shopping with us!'),
            'return_policy' => $this->nullableString($get('return_policy')),
            'exchange_policy' => $this->nullableString($get('exchange_policy')),
            'logo_path' => $store->logo_path,
            'theme_color' => $store->theme_color,
            'store_name' => $store->name,
            'store_code' => $store->code,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $str = trim((string) $value);

        return $str === '' ? null : $str;
    }
}
