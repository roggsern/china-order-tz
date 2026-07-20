<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\JsonResponse;

/**
 * Legacy Payment-model initiate endpoint.
 * Production payments use POST /payments/start/{order} (Payment Orchestrator).
 */
class PaymentController extends Controller
{
    public function initiate(Payment $payment): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'This payment endpoint is retired. Start payment with POST /api/v1/payments/start/{order}.',
            'deprecated' => true,
            'replacement' => '/api/v1/payments/start/{order}',
            'payment_id' => $payment->id,
        ], 410);
    }
}
