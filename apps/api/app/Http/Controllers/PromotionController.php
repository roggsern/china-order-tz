<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\ApplyPromotionRequest;
use App\Http\Requests\Customer\ValidatePromotionRequest;
use App\Http\Resources\CheckoutSessionResource;
use App\Http\Resources\PromotionResource;
use App\Models\CheckoutSession;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutOrchestrator;
use App\Services\Promotions\DiscountResolver;
use Illuminate\Http\JsonResponse;

class PromotionController extends Controller
{
    public function __construct(
        private readonly DiscountResolver $discounts,
        private readonly CheckoutOrchestrator $checkout,
        private readonly CartService $carts,
    ) {}

    public function validateCode(ValidatePromotionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $code = $request->validated('code');

        if ($request->filled('checkout_session_id')) {
            $session = CheckoutSession::query()->findOrFail($request->validated('checkout_session_id'));
            abort_unless($session->user_id === $user->id, 404);
            $cart = $this->carts->loadCart($session->cart()->firstOrFail());
            $base = $this->checkout->validateCartLines($cart);
        } else {
            $cart = $user->activeCart;
            if ($cart === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Active cart not found.',
                ], 422);
            }
            $cart = $this->carts->loadCart($cart);
            $base = $this->checkout->validateCartLines($cart);
        }

        $result = $this->discounts->validateCoupon(
            $user,
            $cart,
            $code,
            $base['subtotal'],
            $base['currency'],
        );

        $eligible = $result['failures'] === [];

        return response()->json([
            'success' => true,
            'data' => [
                'eligible' => $eligible,
                'failures' => $result['failures'],
                'promotion' => new PromotionResource($result['promotion']),
                'discount_total' => $result['resolution']->discountTotal,
                'grand_total' => $result['resolution']->grandTotal(),
                'breakdown' => $result['resolution']->toBreakdown(),
            ],
        ]);
    }

    public function apply(ApplyPromotionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $session = CheckoutSession::query()->findOrFail($request->validated('checkout_session_id'));
        $updated = $this->checkout->applyPromotion($user, $session, $request->validated('code'));

        return response()->json([
            'success' => true,
            'message' => 'Promotion applied.',
            'data' => new CheckoutSessionResource($updated),
        ]);
    }
}
