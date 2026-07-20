<?php

namespace App\Services\Shipments;

use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShipmentNumberGenerator
{
    public function generate(?\DateTimeInterface $date = null): string
    {
        $date ??= now();
        $prefix = 'COTZ-SHIP-'.$date->format('Ymd').'-';

        return DB::transaction(function () use ($prefix): string {
            $latest = Shipment::query()
                ->withTrashed()
                ->where('shipment_number', 'like', $prefix.'%')
                ->orderByDesc('shipment_number')
                ->lockForUpdate()
                ->value('shipment_number');

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
