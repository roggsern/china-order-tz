<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Legacy Payment-model initiate endpoint.
 * Production payments use POST /payments/start/{order} (Payment Orchestrator).
 */
class PaymentController extends Controller
{
    public function initiate(Payment $payment): JsonResponse
    {
        return ApiResponse::error(
            message: 'This payment endpoint is retired. Start payment with POST /api/v1/payments/start/{order}.',
            code: 'business_rule_violated',
            status: 410,
            extra: [
                'deprecated' => true,
                'replacement' => '/api/v1/payments/start/{order}',
                'payment_id' => $payment->id,
            ],
        );
    }
}
