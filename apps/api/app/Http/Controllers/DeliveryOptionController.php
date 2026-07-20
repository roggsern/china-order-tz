<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerOrders\StoreDeliveryOptionRequest;
use App\Http\Requests\CustomerOrders\UpdateDeliveryOptionRequest;
use App\Http\Resources\DeliveryOptionResource;
use App\Models\Order;
use App\Models\User;
use App\Services\Delivery\DeliveryOptionEngine;
use Illuminate\Http\JsonResponse;

class DeliveryOptionController extends Controller
{
    public function show(Order $order, DeliveryOptionEngine $engine): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $option = $engine->show($user, $order);
        $available = $engine->availableOptions($order);

        return response()->json([
            'success' => true,
            'data' => [
                'delivery_option' => $option
                    ? new DeliveryOptionResource($option)
                    : null,
                'available' => $available,
            ],
        ]);
    }

    public function store(
        Order $order,
        StoreDeliveryOptionRequest $request,
        DeliveryOptionEngine $engine,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        $option = $engine->select($user, $order, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Delivery option selected.',
            'data' => new DeliveryOptionResource($option),
        ], 201);
    }

    public function update(
        Order $order,
        UpdateDeliveryOptionRequest $request,
        DeliveryOptionEngine $engine,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        $option = $engine->update($user, $order, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Delivery option updated.',
            'data' => new DeliveryOptionResource($option),
        ]);
    }
}
