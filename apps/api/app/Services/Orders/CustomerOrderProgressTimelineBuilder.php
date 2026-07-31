<?php

namespace App\Services\Orders;

use App\Enums\CustomerOrderProgressKey;

/**
 * Customer-safe tracking timeline rows derived from CustomerOrderProgressResolver output.
 * Customer APIs must use this (via progress projection) — never raw ShipmentStatus timelines.
 */
class CustomerOrderProgressTimelineBuilder
{
    /** @var array<string, string> */
    private const DESCRIPTIONS = [
        'AWAITING_PAYMENT' => 'Complete payment to confirm your order.',
        'ORDER_CONFIRMED' => 'Your order is confirmed and queued for fulfilment.',
        'PREPARING' => 'We are preparing your items for shipping.',
        'READY_TO_SHIP' => 'Your order is packed and ready to leave our warehouse.',
        'SHIPPED' => 'Your order is on its way to you.',
        'DELIVERED' => 'Your order has been delivered.',
        'SENT_TO_AGENT' => 'Your order has been sent to your nominated agent.',
        'DELIVERED_TO_AGENT' => 'Your order has been delivered to your nominated agent.',
        'CANCELLED' => 'This order has been cancelled.',
        'REFUND_PENDING' => 'Your refund is being processed.',
        'REFUNDED' => 'Your refund has been completed.',
    ];

    /** @var array<string, string> */
    private const AGENT_DELIVERY_DESCRIPTIONS = [
        'ORDER_CONFIRMED' => 'Your order has been confirmed.',
        'PREPARING' => 'We are preparing your items.',
        'SENT_TO_AGENT' => 'Your order has been sent to your nominated agent.',
        'DELIVERED_TO_AGENT' => 'Your order has been delivered to your nominated agent.',
    ];

    /** @var array<string, string> */
    private const LOCAL_DELIVERY_DESCRIPTIONS = [
        'ORDER_CONFIRMED' => 'Your order is confirmed and queued for preparation.',
        'PREPARING' => 'We are preparing your order.',
        'READY_TO_SHIP' => 'Your order is ready. We will notify you according to your collection preference.',
        'DELIVERED' => 'Your order is complete. Thank you for shopping with us.',
    ];

    /** @var array<string, string> */
    private const COMPANY_SHIPPING_DESCRIPTIONS = [
        'ORDER_CONFIRMED' => 'Your order is confirmed and queued for fulfilment.',
        'PREPARING' => 'We are preparing your items for shipping.',
        'SHIPPED' => 'Your order is on its way to Tanzania.',
        'ARRIVED_TANZANIA' => 'Your order has arrived in Tanzania.',
        'CHOOSE_RECEIVING_METHOD' => 'Choose how you would like to receive your order.',
        'DELIVERED' => 'Your order is complete. Thank you for shopping with us.',
    ];

    /**
     * @param  array{
     *     current_key: string,
     *     current_label: string,
     *     steps: list<array{key: string, label: string, completed: bool}>
     * }  $progress
     * @return list<array{
     *     key: string,
     *     step: string,
     *     completed: bool,
     *     completed_at: null,
     *     description: string
     * }>
     */
    public function build(array $progress): array
    {
        $steps = [];
        $isAgentDelivery = $this->isAgentDeliveryProgress($progress);
        $isLocalDelivery = $this->isLocalDeliveryProgress($progress);
        $isCompanyShipping = $this->isCompanyShippingProgress($progress);

        foreach ($progress['steps'] as $step) {
            $key = (string) ($step['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $steps[] = [
                'key' => $key,
                'step' => (string) ($step['label'] ?? $key),
                'completed' => (bool) ($step['completed'] ?? false),
                'completed_at' => null,
                'description' => $this->descriptionForKey($key, $isAgentDelivery, $isLocalDelivery, $isCompanyShipping, $step),
            ];
        }

        return $steps;
    }

    /**
     * @param  array{key?: string, label?: string}  $step
     */
    private function descriptionForKey(string $key, bool $isAgentDelivery, bool $isLocalDelivery, bool $isCompanyShipping, array $step): string
    {
        if ($isAgentDelivery && isset(self::AGENT_DELIVERY_DESCRIPTIONS[$key])) {
            return self::AGENT_DELIVERY_DESCRIPTIONS[$key];
        }

        if ($isLocalDelivery && isset(self::LOCAL_DELIVERY_DESCRIPTIONS[$key])) {
            return self::LOCAL_DELIVERY_DESCRIPTIONS[$key];
        }

        if ($isCompanyShipping && isset(self::COMPANY_SHIPPING_DESCRIPTIONS[$key])) {
            return self::COMPANY_SHIPPING_DESCRIPTIONS[$key];
        }

        return self::DESCRIPTIONS[$key] ?? (string) ($step['label'] ?? $key);
    }

    /**
     * @param  array{steps?: list<array{key?: string}>}  $progress
     */
    private function isAgentDeliveryProgress(array $progress): bool
    {
        foreach ($progress['steps'] ?? [] as $step) {
            if (($step['key'] ?? '') === CustomerOrderProgressKey::SentToAgent->value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{steps?: list<array{key?: string}>}  $progress
     */
    private function isLocalDeliveryProgress(array $progress): bool
    {
        $keys = array_column($progress['steps'] ?? [], 'key');

        return in_array(CustomerOrderProgressKey::ReadyToShip->value, $keys, true)
            && ! in_array(CustomerOrderProgressKey::Shipped->value, $keys, true)
            && ! in_array(CustomerOrderProgressKey::SentToAgent->value, $keys, true);
    }

    /**
     * @param  array{steps?: list<array{key?: string}>}  $progress
     */
    private function isCompanyShippingProgress(array $progress): bool
    {
        $keys = array_column($progress['steps'] ?? [], 'key');

        return in_array(CustomerOrderProgressKey::ArrivedTanzania->value, $keys, true)
            || in_array(CustomerOrderProgressKey::ChooseReceivingMethod->value, $keys, true);
    }

    /**
     * Backward-compatible unified timeline shape for clients expecting composed entries.
     *
     * @param  array{
     *     current_key: string,
     *     current_label: string,
     *     steps: list<array{key: string, label: string, completed: bool}>
     * }  $progress
     * @return list<array<string, mixed>>
     */
    public function buildUnified(array $progress): array
    {
        return array_map(
            fn (array $row): array => [
                'code' => strtolower($row['key']),
                'label' => $row['step'],
                'description' => $row['description'],
                'visibility' => 'customer',
                'completed' => $row['completed'],
            ],
            $this->build($progress),
        );
    }
}
