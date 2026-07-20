<?php

namespace App\Services\Notifications;

/**
 * Replaces {{variable}} tokens in template subjects/bodies.
 */
class NotificationRenderer
{
    /**
     * @param  array<string, mixed>  $variables
     */
    public function render(string $content, array $variables): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            function (array $matches) use ($variables): string {
                $key = $matches[1];
                if (! array_key_exists($key, $variables)) {
                    return $matches[0];
                }

                $value = $variables[$key];

                if ($value === null) {
                    return '';
                }

                if (is_bool($value)) {
                    return $value ? 'true' : 'false';
                }

                if (is_scalar($value)) {
                    return (string) $value;
                }

                return $matches[0];
            },
            $content,
        );
    }
}
