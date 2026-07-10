<?php

namespace Tests\Unit\Payments\Nmb;

use App\Models\Payment;
use App\Payments\Gateways\Nmb\NmbVerificationMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NmbVerificationMapperTest extends TestCase
{
    use RefreshDatabase;

    private NmbVerificationMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = app(NmbVerificationMapper::class);
    }

    public function test_maps_successful_verification(): void
    {
        $payment = Payment::factory()->nmb()->create([
            'reference' => 'PAY-2026-000123',
            'amount' => 75000,
            'currency' => 'TZS',
        ]);

        $result = $this->mapper->fromResponse([
            'result' => 'SUCCESS',
            'order' => [
                'id' => 'PAY-2026-000123',
                'amount' => '75000.00',
                'currency' => 'TZS',
            ],
            'transaction' => [
                'id' => 'TRANS000123',
            ],
        ], $payment);

        $this->assertTrue($result->verified);
        $this->assertSame('PAY-2026-000123', $result->orderId);
        $this->assertSame('TRANS000123', $result->transactionId);
    }

    public function test_rejects_mismatched_order_reference(): void
    {
        $payment = Payment::factory()->nmb()->create([
            'reference' => 'PAY-2026-000123',
            'amount' => 75000,
            'currency' => 'TZS',
        ]);

        $result = $this->mapper->fromResponse([
            'result' => 'SUCCESS',
            'order' => [
                'id' => 'PAY-2026-000999',
                'amount' => '75000.00',
                'currency' => 'TZS',
            ],
        ], $payment);

        $this->assertFalse($result->verified);
    }

    public function test_rejects_gateway_failure(): void
    {
        $payment = Payment::factory()->nmb()->create([
            'reference' => 'PAY-2026-000123',
        ]);

        $result = $this->mapper->fromResponse([
            'result' => 'FAILURE',
            'error' => [
                'explanation' => 'Order not found.',
            ],
        ], $payment);

        $this->assertFalse($result->verified);
        $this->assertSame('Order not found.', $result->message);
    }
}
