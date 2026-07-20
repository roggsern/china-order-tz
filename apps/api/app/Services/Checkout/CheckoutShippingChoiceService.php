<?php

namespace App\Services\Checkout;

use App\Enums\CheckoutSessionStatus;
use App\Enums\CommerceChannelCode;
use App\Enums\DeliveryShippingMethod;
use App\Enums\DeliveryType;
use App\Enums\ShippingMethod;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CheckoutSession;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Commerce\CommerceChannelResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Explicit pre-payment shipping choice for checkout sessions.
 * COMPANY_SHIPPING charges cart freight; CUSTOMER_AGENT / TZ options keep shipping_total = 0.
 */
class CheckoutShippingChoiceService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CheckoutOrchestrator $orchestrator,
        private readonly CommerceChannelResolver $commerceChannelResolver,
    ) {}

    /**
     * @param  array{
     *     shipping_choice: string,
     *     shipping_method?: string|null,
     *     agent_name?: string|null,
     *     agent_contact?: string|null
     * }  $input
     */
    public function apply(User $user, CheckoutSession $session, array $input): CheckoutSession
    {
        $session = $this->orchestrator->show($user, $session);
        $cart = $this->cartService->loadCart($session->cart()->firstOrFail());
        $channel = $this->commerceChannelResolver->assertCartSingleChannel($cart);
        $channelCode = CommerceChannelCode::tryFrom((string) $channel->code)
            ?? CommerceChannelCode::ChinaImport;

        $choice = DeliveryType::tryFrom((string) ($input['shipping_choice'] ?? ''));
        if ($choice === null) {
            throw ValidationException::withMessages([
                'shipping_choice' => ['Select a shipping option before payment.'],
            ]);
        }

        $this->assertChoiceAllowedForChannel($choice, $channelCode);

        $method = null;
        $agentName = isset($input['agent_name']) ? trim((string) $input['agent_name']) : null;
        $agentContact = isset($input['agent_contact']) ? trim((string) $input['agent_contact']) : null;

        return DB::transaction(function () use (
            $user,
            $session,
            $cart,
            $choice,
            $input,
            &$method,
            $agentName,
            $agentContact,
        ): CheckoutSession {
            if ($choice === DeliveryType::CompanyShipping) {
                $method = DeliveryShippingMethod::tryFrom((string) ($input['shipping_method'] ?? ''));
                if ($method === null) {
                    throw ValidationException::withMessages([
                        'shipping_method' => ['Company shipping requires air or sea.'],
                    ]);
                }
                $this->applyCompanyFreightToCart($cart, ShippingMethod::from($method->value));
                $agentName = null;
                $agentContact = null;
            } elseif ($choice === DeliveryType::CustomerAgent) {
                $this->clearCompanyFreightFromCart($cart);
                $method = null;
            } else {
                $this->clearCompanyFreightFromCart($cart);
                $method = null;
                $agentName = null;
                $agentContact = null;
            }

            $cart = $this->cartService->loadCart($cart);
            $totals = $this->orchestrator->validateCart(
                $cart,
                $user,
                $session->applied_promotion_code,
                $session->promotion_id,
            );

            if ($choice === DeliveryType::CompanyShipping
                && bccomp((string) $totals['shipping_total'], '0.00', 2) <= 0
            ) {
                throw ValidationException::withMessages([
                    'shipping' => ['Company shipping requires a valid shipping price on cart items.'],
                ]);
            }

            if (in_array($choice, [DeliveryType::CustomerAgent, DeliveryType::SelfPickup, DeliveryType::NegotiatedDelivery], true)) {
                $totals['shipping_total'] = '0.00';
                $totals['grand_total'] = bcsub(
                    bcadd(bcadd($totals['subtotal'], '0.00', 2), $totals['tax_total'] ?? '0.00', 2),
                    $totals['discount_total'] ?? '0.00',
                    2,
                );
            }

            $session->fill([
                'shipping_choice' => $choice->value,
                'shipping_method' => $method?->value,
                'agent_name' => filled($agentName) ? $agentName : null,
                'agent_contact' => filled($agentContact) ? $agentContact : null,
                'cart_fingerprint' => $this->fingerprint($cart, $choice, $method?->value),
            ]);
            $session->applyTotals($totals);
            $session->status = CheckoutSessionStatus::Validated;
            $session->expires_at = now()->addMinutes(CheckoutOrchestrator::SESSION_TTL_MINUTES);
            $session->save();

            return $this->orchestrator->loadSession($session->fresh() ?? $session);
        });
    }

    public function assertReadyForOrder(CheckoutSession $session): void
    {
        if (! filled($session->shipping_choice)) {
            throw ValidationException::withMessages([
                'shipping_choice' => ['Select a shipping option before creating the order.'],
            ]);
        }

        $choice = DeliveryType::tryFrom((string) $session->shipping_choice);
        if ($choice === null) {
            throw ValidationException::withMessages([
                'shipping_choice' => ['Invalid shipping choice on checkout session.'],
            ]);
        }

        $cart = $this->cartService->loadCart($session->cart()->firstOrFail());
        $current = $this->fingerprint($cart, $choice, $session->shipping_method);

        if ($session->cart_fingerprint !== null && ! hash_equals((string) $session->cart_fingerprint, $current)) {
            throw ValidationException::withMessages([
                'session' => ['Checkout totals are stale. Refresh checkout and confirm shipping again.'],
            ]);
        }

        if ($choice === DeliveryType::CompanyShipping) {
            if (! filled($session->shipping_method)) {
                throw ValidationException::withMessages([
                    'shipping_method' => ['Company shipping requires air or sea.'],
                ]);
            }
            if (bccomp((string) $session->shipping_total, '0.00', 2) <= 0) {
                throw ValidationException::withMessages([
                    'shipping' => ['Company shipping total must be greater than zero.'],
                ]);
            }
        }

        if (in_array($choice, [DeliveryType::CustomerAgent, DeliveryType::SelfPickup, DeliveryType::NegotiatedDelivery], true)
            && bccomp((string) $session->shipping_total, '0.00', 2) !== 0
        ) {
            throw ValidationException::withMessages([
                'shipping' => ['This shipping choice must have zero company shipping charges.'],
            ]);
        }
    }

    public function fingerprint(Cart $cart, DeliveryType $choice, ?string $shippingMethod): string
    {
        $cart->loadMissing('items');
        $parts = [$choice->value, (string) $shippingMethod];

        foreach ($cart->items->sortBy('id') as $item) {
            /** @var CartItem $item */
            $parts[] = implode(':', [
                $item->product_variant_id,
                $item->quantity,
                (string) ($item->price_snapshot ?? $item->unit_price),
                $item->shipping_method instanceof ShippingMethod
                    ? $item->shipping_method->value
                    : (string) $item->shipping_method,
                (string) $item->shipping_price,
            ]);
        }

        return hash('sha256', implode('|', $parts));
    }

    private function assertChoiceAllowedForChannel(DeliveryType $choice, CommerceChannelCode $channelCode): void
    {
        $allowed = match ($channelCode) {
            CommerceChannelCode::ChinaImport => [DeliveryType::CompanyShipping, DeliveryType::CustomerAgent],
            CommerceChannelCode::TzLocal => [DeliveryType::SelfPickup, DeliveryType::NegotiatedDelivery],
        };

        if (! in_array($choice, $allowed, true)) {
            throw ValidationException::withMessages([
                'shipping_choice' => [
                    "Shipping choice [{$choice->value}] is not allowed for this cart's commerce channel.",
                ],
            ]);
        }
    }

    private function applyCompanyFreightToCart(Cart $cart, ShippingMethod $method): void
    {
        $cart->loadMissing(['items.product']);

        foreach ($cart->items as $item) {
            $product = $item->product;
            if ($product === null || ! $product->requiresChinaShipping()) {
                continue;
            }

            $price = $product->shippingPriceForMethod($method->value);
            if ($price === null) {
                throw ValidationException::withMessages([
                    'shipping' => ["No {$method->value} shipping price for {$product->name}."],
                ]);
            }

            $item->forceFill([
                'shipping_method' => $method,
                'shipping_price' => $price,
            ])->save();
        }
    }

    private function clearCompanyFreightFromCart(Cart $cart): void
    {
        $cart->loadMissing('items');
        foreach ($cart->items as $item) {
            $item->forceFill([
                'shipping_method' => null,
                'shipping_price' => null,
            ])->save();
        }
    }
}
