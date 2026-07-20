<?php

namespace App\Services\Analytics\Support;

/**
 * Library-agnostic chart payload. Frontends map type → chart component.
 */
final class ChartSeries
{
    /**
     * @param  list<array{x: string|int, y: float|int, label?: string}>  $points
     * @return array{type: string, key: string, label: string, series: list<array{name: string, points: list<array{x: string|int, y: float|int, label?: string}>}>}
     */
    public static function make(
        string $type,
        string $key,
        string $label,
        array $points,
        string $seriesName = 'Value',
    ): array {
        return [
            'type' => $type,
            'key' => $key,
            'label' => $label,
            'series' => [
                [
                    'name' => $seriesName,
                    'points' => $points,
                ],
            ],
        ];
    }

    /**
     * @param  list<array{name: string, points: list<array{x: string|int, y: float|int, label?: string}>}>  $series
     * @return array{type: string, key: string, label: string, series: list<array{name: string, points: list<array{x: string|int, y: float|int, label?: string}>}>}
     */
    public static function multi(string $type, string $key, string $label, array $series): array
    {
        return [
            'type' => $type,
            'key' => $key,
            'label' => $label,
            'series' => $series,
        ];
    }

    /**
     * @param  array<string, float|int>  $map  label => value
     * @return list<array{x: string, y: float|int, label: string}>
     */
    public static function fromMap(array $map): array
    {
        $points = [];
        foreach ($map as $label => $value) {
            $points[] = [
                'x' => (string) $label,
                'y' => $value,
                'label' => (string) $label,
            ];
        }

        return $points;
    }
}
