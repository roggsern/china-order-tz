<?php

namespace App\Services\Delivery;

use App\Enums\DeliveryOptionStatus;
use App\Enums\DeliveryShippingMethod;
use App\Enums\DeliveryType;
use App\Models\Order;
use App\Services\ProductShipping\ProductShippingOptionEngine;
use Illuminate\Validation\ValidationException;

/**
 * Validates delivery option payloads against China / Tanzania business rules.
 * Does not calculate shipping prices or create shipments.
 */
class DeliveryOptionValidator
{
    public function __construct(
        private readonly DeliveryTypeResolver $typeResolver,
        private readonly ProductShippingOptionEngine $shippingOptionEngine,
    ) {}

    /**
     * @param  array{
     *     delivery_type: string,
     *     shipping_method?: string|null,
     *     agent_name?: string|null,
     *     agent_contact?: string|null,
     *     notes?: string|null,
     *     delivery_status?: string|null
     * }  $input
     * @return array{
     *     delivery_type: DeliveryType,
     *     shipping_method: DeliveryShippingMethod|null,
     *     agent_name: string|null,
     *     agent_contact: string|null,
     *     notes: string|null,
     *     delivery_status: DeliveryOptionStatus|null
     * }
     */
    public function validateForOrder(Order $order, array $input, bool $requireAgentDetails = true): array
    {
        $type = DeliveryType::tryFrom((string) ($input['delivery_type'] ?? ''));

        if ($type === null) {
            throw ValidationException::withMessages([
                'delivery_type' => ['Invalid delivery type.'],
            ]);
        }

        if (! $this->typeResolver->allows($order, $type)) {
            $market = $this->typeResolver->resolveMarket($order);

            throw ValidationException::withMessages([
                'delivery_type' => [
                    "Delivery type [{$type->value}] is not allowed for {$market->label()} orders.",
                ],
            ]);
        }

        $shippingMethod = null;
        $agentName = isset($input['agent_name']) ? trim((string) $input['agent_name']) : null;
        $agentContact = isset($input['agent_contact']) ? trim((string) $input['agent_contact']) : null;
        $agentCompany = isset($input['agent_company']) ? trim((string) $input['agent_company']) : null;
        $agentPhone = isset($input['agent_phone']) ? trim((string) $input['agent_phone']) : null;
        $agentEmail = isset($input['agent_email']) ? trim((string) $input['agent_email']) : null;
        $notes = isset($input['notes']) ? trim((string) $input['notes']) : null;

        if ($type === DeliveryType::CompanyShipping) {
            $shippingMethod = DeliveryShippingMethod::tryFrom((string) ($input['shipping_method'] ?? ''));

            if ($shippingMethod === null) {
                throw ValidationException::withMessages([
                    'shipping_method' => ['Company shipping requires shipping_method: air or sea.'],
                ]);
            }

            $available = collect($this->shippingOptionEngine->availableMethodsForOrder($order))
                ->pluck('value')
                ->all();

            if ($available !== [] && ! in_array($shippingMethod->value, $available, true)) {
                throw ValidationException::withMessages([
                    'shipping_method' => [
                        "Shipping method [{$shippingMethod->value}] is not available for products on this order.",
                    ],
                ]);
            }

            if (filled($agentName) || filled($agentContact)) {
                throw ValidationException::withMessages([
                    'agent_name' => ['Agent details are not used for company shipping.'],
                ]);
            }

            $agentName = null;
            $agentContact = null;
        }

        if ($type === DeliveryType::CustomerAgent) {
            if ($requireAgentDetails && ! filled($agentName)) {
                throw ValidationException::withMessages([
                    'agent_name' => ['Agent name is required for customer agent delivery.'],
                ]);
            }

            if ($requireAgentDetails && ! filled($agentContact)) {
                throw ValidationException::withMessages([
                    'agent_contact' => ['Agent contact is required for customer agent delivery.'],
                ]);
            }

            if (filled($input['shipping_method'] ?? null)) {
                throw ValidationException::withMessages([
                    'shipping_method' => ['Shipping method is not used for customer agent delivery.'],
                ]);
            }

            $shippingMethod = null;
        }

        if ($type->isTanzaniaType()) {
            if (filled($input['shipping_method'] ?? null)) {
                throw ValidationException::withMessages([
                    'shipping_method' => ['Shipping method is not used for Tanzania delivery options.'],
                ]);
            }

            if (filled($agentName) || filled($agentContact)) {
                throw ValidationException::withMessages([
                    'agent_name' => ['Agent details are not used for Tanzania delivery options.'],
                ]);
            }

            $shippingMethod = null;
            $agentName = null;
            $agentContact = null;
        }

        $status = null;
        if (isset($input['delivery_status']) && filled($input['delivery_status'])) {
            $status = DeliveryOptionStatus::tryFrom((string) $input['delivery_status']);
            if ($status === null) {
                throw ValidationException::withMessages([
                    'delivery_status' => ['Invalid delivery status.'],
                ]);
            }
        }

        return [
            'delivery_type' => $type,
            'shipping_method' => $shippingMethod,
            'agent_name' => $agentName !== '' ? $agentName : null,
            'agent_contact' => $agentContact !== '' ? $agentContact : null,
            'agent_company' => filled($agentCompany) ? $agentCompany : null,
            'agent_phone' => filled($agentPhone) ? $agentPhone : null,
            'agent_email' => filled($agentEmail) ? $agentEmail : null,
            'notes' => $notes !== '' ? $notes : null,
            'delivery_status' => $status,
        ];
    }
}
