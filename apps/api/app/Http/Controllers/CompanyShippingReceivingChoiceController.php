<?php

namespace App\Http\Controllers;

use App\Enums\LastMileReceivingMethod;
use App\Http\Requests\CustomerOrders\StoreReceivingMethodRequest;
use App\Http\Resources\DeliveryOptionResource;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\CompanyShippingReceivingChoiceService;
use Illuminate\Http\JsonResponse;

class CompanyShippingReceivingChoiceController extends Controller
{
    public function store(
        Order $order,
        StoreReceivingMethodRequest $request,
        CompanyShippingReceivingChoiceService $service,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        $method = LastMileReceivingMethod::from($request->validated('receiving_method'));
        $option = $service->select($order, $user, $method);

        return response()->json([
            'success' => true,
            'message' => 'Receiving method selected.',
            'data' => [
                'delivery_option' => new DeliveryOptionResource($option),
                'receiving_choice' => $service->snapshot($order->fresh(['deliveryOption', 'fulfillment.shipment']), $user),
            ],
        ]);
    }
}
