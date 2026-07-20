<?php

namespace App\Services\Warehouse;

use App\Models\WarehouseJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WarehouseJobNumberGenerator
{
    public function generate(?\DateTimeInterface $date = null): string
    {
        $date ??= now();
        $prefix = 'COTZ-WH-'.$date->format('Ymd').'-';

        return DB::transaction(function () use ($prefix): string {
            $latest = WarehouseJob::query()
                ->where('job_number', 'like', $prefix.'%')
                ->orderByDesc('job_number')
                ->lockForUpdate()
                ->value('job_number');

            $sequence = 1;
            if (is_string($latest) && Str::startsWith($latest, $prefix)) {
                $tail = substr($latest, strlen($prefix));
                if (ctype_digit($tail)) {
                    $sequence = ((int) $tail) + 1;
                }
            }

            return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
        });
    }
}
