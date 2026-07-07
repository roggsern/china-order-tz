<?php

namespace App\Http\Controllers;

use App\Actions\CustomerOrders\ListCustomerOrdersAction;
use App\Http\Requests\CustomerOrders\IndexCustomerOrdersRequest;
use App\Http\Resources\CustomerOrderResource;
use App\Models\User;
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
}
