<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEventType;
use App\Models\NotificationTemplate;

class NotificationTemplateEngine
{
    public function findActive(string $key, ?NotificationChannel $channel = null): ?NotificationTemplate
    {
        $query = NotificationTemplate::query()
            ->where('key', $key)
            ->where('is_active', true);

        if ($channel !== null) {
            $query->where('channel', $channel->value);
        }

        return $query->first();
    }

    public function resolveForEvent(
        NotificationEventType $event,
        NotificationChannel $channel,
    ): ?NotificationTemplate {
        $key = $event->defaultTemplateKey($channel);

        $template = $this->findActive($key, $channel);
        if ($template !== null) {
            return $template;
        }

        // Fallback: any active template matching event prefix + channel.
        return NotificationTemplate::query()
            ->where('channel', $channel->value)
            ->where('is_active', true)
            ->where('key', 'like', $event->value.'.%')
            ->first();
    }

    /**
     * @return array{subject: string|null, body: string, title: string}
     */
    public function preview(NotificationTemplate $template, array $variables, NotificationRenderer $renderer): array
    {
        $body = $renderer->render($template->body, $variables);
        $subject = $template->subject !== null
            ? $renderer->render($template->subject, $variables)
            : null;

        return [
            'subject' => $subject,
            'body' => $body,
            'title' => $subject ?? $template->name,
        ];
    }
}
