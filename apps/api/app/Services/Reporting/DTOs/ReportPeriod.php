<?php

namespace App\Services\Reporting\DTOs;

/**
 * Immutable date window for metrics/reports (read-only analytics).
 */
final class ReportPeriod
{
    public function __construct(
        public readonly \Carbon\CarbonInterface $from,
        public readonly \Carbon\CarbonInterface $to,
    ) {}

    public static function fromInput(?string $from, ?string $to, int $defaultDays = 30): self
    {
        $end = filled($to) ? \Illuminate\Support\Carbon::parse($to)->endOfDay() : now()->endOfDay();
        $start = filled($from)
            ? \Illuminate\Support\Carbon::parse($from)->startOfDay()
            : $end->copy()->subDays($defaultDays - 1)->startOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return new self($start, $end);
    }

    public function today(): self
    {
        return new self(now()->startOfDay(), now()->endOfDay());
    }
}
