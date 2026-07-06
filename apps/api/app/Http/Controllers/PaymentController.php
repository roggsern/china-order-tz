<?php

namespace App\Http\Controllers;

use App\Actions\Payments\InitiatePaymentAction;
use App\Http\Resources\PaymentInitiateResource;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function initiate(Payment $payment, InitiatePaymentAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => new PaymentInitiateResource($action->handle($payment, $user)),
        ]);
    }
}
