<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Payments\SimulateNmbCallbackAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimulateNmbCallbackRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Support\Production\ProductionSafety;
use Illuminate\Http\JsonResponse;

class AdminSimulateNmbCallbackController extends Controller
{
    public function store(
        SimulateNmbCallbackRequest $request,
        Payment $payment,
        SimulateNmbCallbackAction $action,
    ): JsonResponse {
        ProductionSafety::assertNonProductionTooling('NMB callback simulation');

        $result = $action->handle($payment, $request->validated('result'));

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
