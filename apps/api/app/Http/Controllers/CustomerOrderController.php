<?php

namespace App\Http\Controllers;

use App\Actions\CustomerOrders\ListCustomerOrdersAction;
use App\Actions\CustomerOrders\ShowCustomerOrderAction;
use App\Http\Requests\CustomerOrders\IndexCustomerOrdersRequest;
use App\Http\Resources\CustomerOrderDetailResource;
use App\Http\Resources\CustomerOrderResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerOrderController extends Controller
{
    public function index(
        IndexCustomerOrdersRequest $request,
        ListCustomerOrdersAction $action,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = auth()->user();

        return CustomerOrderResource::collection(
            $action->handle(
                $user,
                (int) $request->validated('per_page', 10),
                $request->validated('filter', 'all'),
            )
        )->additional(['success' => true]);
    }

    public function show(Order $order, ShowCustomerOrderAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => new CustomerOrderDetailResource($action->handle($order, $user)),
        ]);
    }
}
