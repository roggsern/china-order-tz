<?php

namespace App\Shipments;

use App\Enums\ShipmentStatus;
use Illuminate\Validation\ValidationException;

class ShipmentStatusTransitionValidator
{
    public function validate(ShipmentStatus $current, ShipmentStatus $target): void
    {
        $timeline = ShipmentStatus::timeline();
        $currentIndex = array_search($current, $timeline, true);
        $targetIndex = array_search($target, $timeline, true);

        if ($currentIndex === false || $targetIndex === false) {
            throw ValidationException::withMessages([
                'shipment_status' => ['Invalid shipment status transition.'],
            ]);
        }

        if ($targetIndex <= $currentIndex) {
            throw ValidationException::withMessages([
                'shipment_status' => ['Cannot move shipment status backward.'],
            ]);
        }

        if ($targetIndex > $currentIndex + 1) {
            throw ValidationException::withMessages([
                'shipment_status' => ['Cannot skip shipment status steps.'],
            ]);
        }
    }
}
