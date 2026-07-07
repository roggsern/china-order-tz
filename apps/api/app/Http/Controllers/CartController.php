<?php

namespace App\Http\Controllers;

use App\Actions\Cart\AddToCartAction;
use App\Actions\Cart\BuyNowAction;
use App\Actions\Cart\ClearCartAction;
use App\Actions\Cart\GetCartAction;
use App\Actions\Cart\RemoveCartItemAction;
use App\Actions\Cart\UpdateCartItemAction;
use App\Http\Requests\Cart\BuyNowRequest;
use App\Http\Requests\Cart\StoreCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Http\Resources\CheckoutPreparationResource;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class CartController extends Controller
{
    public function show(GetCartAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => new CartResource($action->handle($user)),
        ]);
    }

    public function store(StoreCartItemRequest $request, AddToCartAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => new CartResource($action->handle($request, $user)),
        ], 201);
    }

    public function update(
        UpdateCartItemRequest $request,
        CartItem $item,
        UpdateCartItemAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => new CartResource($action->handle($request, $user, $item)),
        ]);
    }

    public function destroyItem(CartItem $item, RemoveCartItemAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => new CartResource($action->handle($user, $item)),
        ]);
    }

    public function destroy(ClearCartAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => new CartResource($action->handle($user)),
        ]);
    }

    public function buyNow(BuyNowRequest $request, BuyNowAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => new CheckoutPreparationResource($action->handle($request, $user)),
        ], 201);
    }
}
