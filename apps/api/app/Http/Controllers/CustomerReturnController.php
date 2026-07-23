<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\StoreReturnRequestRequest;
use App\Http\Resources\CustomerReturnRequestResource;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Returns\ReturnEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerReturnController extends Controller
{
    public function __construct(
        private readonly ReturnEngine $engine,
    ) {}

    public function store(Order $order, StoreReturnRequestRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $return = $this->engine->requestReturn($user, $order, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Return request submitted.',
            'data' => new CustomerReturnRequestResource($return),
        ], 201);
    }

    public function index(): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = auth()->user();

        return CustomerReturnRequestResource::collection(
            $this->engine->paginateForCustomer($user)
        )->additional(['success' => true]);
    }

    public function show(ReturnRequest $returnRequest): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if ($returnRequest->customer_id !== $user->id) {
            abort(404);
        }

        return response()->json([
            'success' => true,
            'data' => new CustomerReturnRequestResource($this->engine->show($returnRequest)),
        ]);
    }
}
