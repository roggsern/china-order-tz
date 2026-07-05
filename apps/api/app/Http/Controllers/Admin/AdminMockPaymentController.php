<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminPayments\ProcessMockPaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminMockPaymentController extends Controller
{
    public function process(
        Payment $payment,
        Request $request,
        ProcessMockPaymentAction $action,
    ): JsonResponse {
        $validated = $request->validate([
            'result' => ['required', 'string', Rule::in(['success', 'failed'])],
        ]);

        $result = $action->handle($payment, $validated['result']);

        if ($result['failed']) {
            return response()->json([
                'success' => true,
                'message' => 'Payment failed.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment processed successfully.',
            'payment' => new PaymentResource($result['payment']),
            'order' => new OrderResource($result['order']),
        ]);
    }
}
