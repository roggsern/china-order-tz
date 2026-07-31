<?php

namespace App\Support\Ops;

/**
 * Production deployment safety checks — reads config only; never exposes secrets.
 */
final class ProductionEnvironmentValidator
{
    /**
     * @return list<string>
     */
    public static function issues(): array
    {
        return array_merge(self::productionConfigIssues(), self::mailIssues());
    }

    /**
     * Payment/debug/webhook safety — excludes mail (see mailIssues()).
     *
     * @return list<string>
     */
    public static function productionConfigIssues(): array
    {
        if (! app()->environment('production')) {
            return [];
        }

        $issues = [];

        if ((bool) config('app.debug')) {
            $issues[] = 'APP_DEBUG must be false in production.';
        }

        if ((string) config('payments.default_gateway') === 'mock') {
            $issues[] = 'PAYMENT_DEFAULT_GATEWAY must not be mock in production.';
        }

        if (! (bool) config('payments.nmb.webhook_require_signature')) {
            $issues[] = 'NMB_WEBHOOK_REQUIRE_SIGNATURE must be true in production.';
        }

        return $issues;
    }

    public static function isProductionConfigHealthy(): bool
    {
        return self::productionConfigIssues() === [];
    }

    /**
     * @return list<string>
     */
    public static function mailIssues(): array
    {
        if (! app()->environment('production')) {
            return [];
        }

        $issues = [];
        $mailer = (string) config('mail.default');
        $host = trim((string) config('mail.mailers.smtp.host'));
        $from = trim((string) config('mail.from.address'));
        $notificationConfigured = (bool) config('notifications.email.configured', false);

        if (in_array($mailer, ['log', 'array'], true)) {
            $issues[] = 'MAIL_MAILER must not be log/array in production.';
        }

        if ($host === '') {
            $issues[] = 'MAIL_HOST is required for production email delivery.';
        }

        if ($from === '' || $from === 'hello@example.com') {
            $issues[] = 'MAIL_FROM_ADDRESS must be set to a production sender address.';
        }

        if (! $notificationConfigured) {
            $issues[] = 'NOTIFICATION_EMAIL_CONFIGURED must be true for password reset, verification, and email-change notifications.';
        }

        return $issues;
    }

    public static function isMailConfigured(): bool
    {
        return self::mailIssues() === [];
    }
}
