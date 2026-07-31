<?php

namespace Tests\Unit\Services\Orders;

use App\Enums\CustomerOrderProgressKey;
use App\Services\Orders\CustomerOrderProgressTimelineBuilder;
use Tests\TestCase;

class CustomerOrderProgressTimelineBuilderTest extends TestCase
{
    public function test_builds_customer_safe_timeline_from_progress(): void
    {
        $progress = [
            'current_key' => CustomerOrderProgressKey::Preparing->value,
            'current_label' => CustomerOrderProgressKey::Preparing->label(),
            'steps' => array_map(
                fn (CustomerOrderProgressKey $step) => [
                    'key' => $step->value,
                    'label' => $step->label(),
                    'completed' => $step->journeyIndex() <= CustomerOrderProgressKey::Preparing->journeyIndex(),
                ],
                CustomerOrderProgressKey::journeySteps(),
            ),
        ];

        $timeline = app(CustomerOrderProgressTimelineBuilder::class)->build($progress);

        $this->assertCount(5, $timeline);
        $this->assertSame('PREPARING', $timeline[1]['key']);
        $this->assertSame('Preparing your order', $timeline[1]['step']);
        $this->assertStringContainsString('preparing your items', strtolower($timeline[1]['description']));
    }

    public function test_build_unified_uses_customer_visibility(): void
    {
        $progress = [
            'current_key' => CustomerOrderProgressKey::OrderConfirmed->value,
            'current_label' => CustomerOrderProgressKey::OrderConfirmed->label(),
            'steps' => [
                [
                    'key' => CustomerOrderProgressKey::OrderConfirmed->value,
                    'label' => CustomerOrderProgressKey::OrderConfirmed->label(),
                    'completed' => true,
                ],
            ],
        ];

        $unified = app(CustomerOrderProgressTimelineBuilder::class)->buildUnified($progress);

        $this->assertSame('customer', $unified[0]['visibility']);
        $this->assertSame('order_confirmed', $unified[0]['code']);
        $this->assertSame('Order confirmed', $unified[0]['label']);
    }

    public function test_builds_local_manual_logistics_descriptions(): void
    {
        $progress = [
            'current_key' => CustomerOrderProgressKey::ReadyToShip->value,
            'current_label' => CustomerOrderProgressKey::ReadyToShip->localLabel(),
            'steps' => array_map(
                fn (CustomerOrderProgressKey $step) => [
                    'key' => $step->value,
                    'label' => $step->localLabel(),
                    'completed' => $step->localJourneyIndex() <= CustomerOrderProgressKey::ReadyToShip->localJourneyIndex(),
                ],
                CustomerOrderProgressKey::localJourneySteps(),
            ),
        ];

        $timeline = app(CustomerOrderProgressTimelineBuilder::class)->build($progress);

        $this->assertCount(4, $timeline);
        $this->assertSame('Order ready', $timeline[2]['step']);
        $this->assertStringContainsString('collection preference', strtolower($timeline[2]['description']));
        $this->assertSame('Completed', $timeline[3]['step']);
    }
}
