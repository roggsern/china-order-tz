<?php

namespace App\Services\Audit;

/**
 * Formats activity descriptions and old/new value diffs for API/UI.
 */
class ActivityLogFormatter
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @return list<array{field: string, old: mixed, new: mixed}>
     */
    public function changes(?array $oldValues, ?array $newValues): array
    {
        $oldValues ??= [];
        $newValues ??= [];
        $keys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
        sort($keys);

        $changes = [];
        foreach ($keys as $key) {
            $old = $oldValues[$key] ?? null;
            $new = $newValues[$key] ?? null;
            if ($old === $new) {
                continue;
            }
            $changes[] = [
                'field' => (string) $key,
                'old' => $old,
                'new' => $new,
            ];
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    public function diffAttributes(array $before, array $after): array
    {
        $old = [];
        $new = [];

        foreach ($after as $key => $value) {
            $previous = $before[$key] ?? null;
            $normalizedPrevious = $this->normalize($previous);
            $normalizedNext = $this->normalize($value);
            if ($normalizedPrevious !== $normalizedNext) {
                $old[$key] = $previous;
                $new[$key] = $value;
            }
        }

        return ['old' => $old, 'new' => $new];
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return $value;
    }
}
