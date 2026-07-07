<?php

namespace Tests\Unit\Shipments;

use App\Enums\ShipmentStatus;
use App\Shipments\ShipmentStatusTransitionValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShipmentStatusTransitionValidatorTest extends TestCase
{
    public function test_allows_next_step_forward_transition(): void
    {
        $validator = new ShipmentStatusTransitionValidator;

        $validator->validate(
            ShipmentStatus::OrderReceived,
            ShipmentStatus::PaymentConfirmed,
        );

        $this->assertTrue(true);
    }

    public function test_rejects_skipped_forward_transition(): void
    {
        $validator = new ShipmentStatusTransitionValidator;

        $this->expectException(ValidationException::class);

        $validator->validate(
            ShipmentStatus::OrderReceived,
            ShipmentStatus::Delivered,
        );
    }

    public function test_rejects_backward_transition(): void
    {
        $validator = new ShipmentStatusTransitionValidator;

        $this->expectException(ValidationException::class);

        $validator->validate(
            ShipmentStatus::ShippedFromChina,
            ShipmentStatus::SupplierProcessing,
        );
    }
}
