<?php

namespace App\Support\Nmb;

use Illuminate\Validation\ValidationException;

class NmbConfigValidator
{
    /**
     * @return array<int, string>
     */
    public function validate(): array
    {
        if (! config('services.nmb.enabled')) {
            return [];
        }

        $errors = [];

        foreach ([
            'base_url',
            'merchant_id',
            'password',
            'return_url',
            'merchant_name',
            'merchant_url',
        ] as $key) {
            if (! filled(config("services.nmb.{$key}"))) {
                $errors[] = "NMB configuration missing: {$key}";
            }
        }

        $environment = (string) config('services.nmb.environment', 'sandbox');
        $baseUrl = strtolower((string) config('services.nmb.base_url'));
        $isProductionRuntime = app()->environment('production')
            || (string) config('app.env') === 'production';

        if ($environment === 'production' || $isProductionRuntime) {
            foreach (['sandbox', 'test', 'localhost'] as $needle) {
                if (str_contains($baseUrl, $needle)) {
                    $errors[] = 'Production NMB environment cannot use sandbox or local base URLs.';
                    break;
                }
            }

            if (! filled(config('services.nmb.webhook.secret'))) {
                $errors[] = 'NMB webhook signature verification is required in production but NMB_WEBHOOK_SECRET is not configured.';
            }
        }

        $signatureRequired = $isProductionRuntime
            || (bool) config('services.nmb.webhook.require_signature', false);

        if ($signatureRequired && ! filled(config('services.nmb.webhook.secret'))) {
            $errors[] = 'NMB webhook signature verification is required but NMB_WEBHOOK_SECRET is not configured.';
        }

        return array_values(array_unique($errors));
    }

    /**
     * @throws ValidationException
     */
    public function assertValid(): void
    {
        $errors = $this->validate();

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'nmb' => $errors,
            ]);
        }
    }

    public function warnIfInvalid(): void
    {
        $errors = $this->validate();

        if ($errors === []) {
            return;
        }

        app(NmbPaymentLogger::class)->warning('nmb.config.invalid', [
            'errors' => $errors,
        ]);
    }
}
