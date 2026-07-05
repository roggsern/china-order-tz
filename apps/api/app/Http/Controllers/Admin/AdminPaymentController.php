<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminPayments\CreatePaymentAction;
use App\Actions\AdminPayments\DeletePaymentAction;
use App\Actions\AdminPayments\GetAdminPaymentsAction;
use App\Actions\AdminPayments\ShowPaymentAction;
use App\Actions\AdminPayments\UpdatePaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentRequest;
use App\Http\Requests\Admin\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminPaymentController extends Controller
{
    public function index(GetAdminPaymentsAction $action): AnonymousResourceCollection
    {
        return PaymentResource::collection($action->handle())
            ->additional(['success' => true]);
    }

    public function store(StorePaymentRequest $request, CreatePaymentAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new PaymentResource($action->handle($request)),
        ], 201);
    }

    public function show(Payment $payment, ShowPaymentAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new PaymentResource($action->handle($payment)),
        ]);
    }

    public function update(
        UpdatePaymentRequest $request,
        Payment $payment,
        UpdatePaymentAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new PaymentResource($action->handle($request, $payment)),
        ]);
    }

    public function destroy(Payment $payment, DeletePaymentAction $action): JsonResponse
    {
        $action->handle($payment);

        return response()->json([
            'success' => true,
            'message' => 'Payment deleted successfully',
        ]);
    }
}
