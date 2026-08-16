<?php

namespace App\Services\Notifications;

/**
 * Replaces {{variable}} tokens in template subjects/bodies.
 */
class NotificationRenderer
{
    public const PLACEHOLDER_PATTERN = '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/';

    /**
     * @param  array<string, mixed>  $variables
     */
    public function render(string $content, array $variables): string
    {
        return (string) preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
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

    /**
     * Return unique placeholder variable names still present after rendering.
     *
     * @return list<string>
     */
    public function unresolvedVariableNames(string $content): array
    {
        if ($content === '') {
            return [];
        }

        if (! preg_match_all(self::PLACEHOLDER_PATTERN, $content, $matches)) {
            return [];
        }

        /** @var list<string> $names */
        $names = array_values(array_unique($matches[1]));

        return $names;
    }
}
