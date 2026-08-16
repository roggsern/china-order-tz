<?php

namespace App\Services\Notifications\Providers;

use App\Enums\NotificationChannel;
use App\Mail\PlatformNotificationMail;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notifications\Contracts\NotificationChannelInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Email channel — delivers via Laravel Mail (Resend / SMTP / configured mailer).
 * Subject/body are already rendered by NotificationDispatcher from DB templates.
 */
class EmailNotificationProvider implements NotificationChannelInterface
{
    public function channel(): string
    {
        return NotificationChannel::Email->value;
    }

    public function providerKey(): string
    {
        return (string) config('notifications.email.driver', 'smtp');
    }

    public function isConfigured(): bool
    {
        if (! (bool) config('notifications.email.configured', false)) {
            return false;
        }

        return filled(config('mail.from.address'));
    }

    /**
     * @return array{success: bool, provider_message_id: string|null, error: string|null}
     */
    public function send(Notification $notification): array
    {
        try {
            if (! $this->isConfigured()) {
                return $this->failure('Not Configured');
            }

            $customerId = $notification->customer_id ?? $notification->user_id;
            if (! filled($customerId)) {
                return $this->failure('Customer missing');
            }

            $customer = User::query()->find($customerId);
            if ($customer === null) {
                return $this->failure('Customer missing');
            }

            $email = trim((string) ($customer->email ?? ''));
            if ($email === '') {
                return $this->failure('Customer email missing');
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->failure('Invalid email address');
            }

            $subject = trim((string) ($notification->title ?? ''));
            if ($subject === '') {
                $subject = (string) ($notification->event_type ?? 'Notification');
            }

            $body = trim((string) ($notification->message ?? ''));
            if ($body === '') {
                return $this->failure('Email body empty');
            }

            $this->persistRecipientSnapshot($notification, $email);

            $mailable = new PlatformNotificationMail($subject, $body);
            Mail::to($email, $customer->name ?: null)->send($mailable);

            $messageId = null;
            try {
                $symfonyMessage = $mailable->getSymfonyMessage();
                $header = $symfonyMessage->getHeaders()->get('Message-ID');
                if ($header !== null) {
                    $messageId = trim((string) $header->getBodyAsString(), '<>');
                }
            } catch (Throwable) {
                $messageId = null;
            }

            return [
                'success' => true,
                'provider_message_id' => filled($messageId) ? $messageId : 'email:'.$notification->id,
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::warning('notification.email.send_failed', [
                'notification_id' => $notification->id,
                'error' => $this->sanitize($e->getMessage()),
            ]);

            return $this->failure($this->sanitize($e->getMessage()));
        }
    }

    private function persistRecipientSnapshot(Notification $notification, string $email): void
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $data['email_recipient_masked'] = $this->maskEmail($email);

        $notification->forceFill(['data' => $data])->save();
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }

        [$local, $domain] = $parts;
        $localMasked = strlen($local) <= 1
            ? '*'
            : substr($local, 0, 1).str_repeat('*', max(strlen($local) - 1, 1));

        return $localMasked.'@'.$domain;
    }

    /**
     * @return array{success: bool, provider_message_id: string|null, error: string|null}
     */
    private function failure(string $error): array
    {
        return [
            'success' => false,
            'provider_message_id' => null,
            'error' => $this->sanitize($error),
        ];
    }

    private function sanitize(string $message): string
    {
        $password = (string) config('mail.mailers.smtp.password', '');
        if ($password !== '') {
            $message = str_replace($password, '[redacted]', $message);
        }

        $username = (string) config('mail.mailers.smtp.username', '');
        if ($username !== '' && strlen($username) > 3) {
            $message = str_replace($username, '[redacted]', $message);
        }

        $resendKey = trim((string) config('services.resend.key', ''));
        if ($resendKey !== '' && strlen($resendKey) > 3) {
            $message = str_replace($resendKey, '[redacted]', $message);
        }

        // Defense-in-depth for Resend-style keys leaked into exception text.
        $message = (string) preg_replace('/\bre_[A-Za-z0-9_]{8,}\b/', '[redacted]', $message);

        return Str::limit($message, 480, '…');
    }
}
