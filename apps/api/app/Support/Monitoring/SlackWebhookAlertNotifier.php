<?php

namespace App\Support\Monitoring;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Slack incoming-webhook foundation. Credentials only from ALERT_SLACK_WEBHOOK_URL.
 */
final class SlackWebhookAlertNotifier implements AlertNotifier
{
    public function notify(string $title, string $severity, array $context = []): void
    {
        $webhook = (string) config('monitoring.alerts.slack_webhook_url', '');
        $safe = SafeContextRedactor::redact($context);

        if ($webhook === '') {
            Log::error('monitoring.alert_slack_misconfigured', [
                'reason' => 'missing_webhook_url',
                'title' => $title,
            ]);
            app(LogAlertNotifier::class)->notify($title, $severity, $safe);

            return;
        }

        $text = sprintf(
            '[%s] %s (%s / %s)',
            strtoupper($severity),
            $title,
            (string) config('monitoring.environment'),
            (string) config('monitoring.release'),
        );

        try {
            $response = Http::timeout(5)->post($webhook, [
                'text' => $text,
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => '*'.$title."*\n```".json_encode($safe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."```",
                        ],
                    ],
                ],
            ]);

            if (! $response->successful()) {
                Log::error('monitoring.alert_slack_failed', [
                    'status' => $response->status(),
                    'title' => $title,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('monitoring.alert_slack_exception', [
                'title' => $title,
                'exception' => $e::class,
            ]);
            app(LogAlertNotifier::class)->notify($title, $severity, $safe);
        }
    }
}
