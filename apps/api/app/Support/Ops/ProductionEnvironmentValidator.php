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
        $mailer = strtolower(trim((string) config('mail.default')));
        $from = trim((string) config('mail.from.address'));
        $notificationConfigured = (bool) config('notifications.email.configured', false);

        if (in_array($mailer, ['log', 'array', ''], true)) {
            $issues[] = 'MAIL_MAILER must not be log/array in production.';
        }

        if ($from === '' || $from === 'hello@example.com') {
            $issues[] = 'MAIL_FROM_ADDRESS must be set to a production sender address.';
        }

        if (! $notificationConfigured) {
            $issues[] = 'NOTIFICATION_EMAIL_CONFIGURED must be true for password reset, verification, and email-change notifications.';
        }

        if ($mailer === 'smtp') {
            $host = trim((string) config('mail.mailers.smtp.host'));
            if ($host === '' || $host === '127.0.0.1' || $host === 'localhost') {
                $issues[] = 'MAIL_HOST is required for production SMTP email delivery.';
            }

            $port = (int) config('mail.mailers.smtp.port');
            if ($port <= 0) {
                $issues[] = 'MAIL_PORT is required for production SMTP email delivery.';
            }
        } elseif ($mailer === 'resend') {
            $key = trim((string) config('services.resend.key', ''));
            if ($key === '') {
                $issues[] = 'RESEND_API_KEY (services.resend.key) must be set when MAIL_MAILER=resend.';
            }
        }

        return $issues;
    }

    public static function isMailConfigured(): bool
    {
        return self::mailIssues() === [];
    }
}
