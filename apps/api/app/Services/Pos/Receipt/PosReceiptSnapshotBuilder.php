<?php

namespace App\Services\Pos\Receipt;

use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PosReceipt;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\User;

/**
 * Builds an immutable printable snapshot from Order / Payment / Store engines.
 * Presentation only — never invents totals independent of the Order.
 */
class PosReceiptSnapshotBuilder
{
    public function __construct(
        private readonly StoreReceiptSettings $settings,
    ) {}

    /**
     * @param  list<array{name: string, variant: ?string, sku: ?string, qty: int, unit_price: string, line_total: string, discount?: string|null}>|null  $preparedLines
     * @return array<string, mixed>
     */
    public function build(
        Order $order,
        Store $store,
        PosSession $session,
        Admin $cashier,
        Payment $payment,
        ?User $customer,
        ?string $change,
        ?float $amountReceived,
        ?array $preparedLines = null,
        ?array $promotion = null,
    ): array {
        $branding = $this->settings->forStore($store);
        $issuedAt = $order->paid_at ?? $order->placed_at ?? now();

        $lines = $preparedLines ?? $order->items->map(fn ($item) => [
            'name' => $item->product_name ?? $item->name ?? 'Item',
            'variant' => $item->variant_name ?? $item->product_variant_name ?? null,
            'sku' => $item->sku ?? $item->variant_sku ?? null,
            'qty' => (int) $item->quantity,
            'unit_price' => number_format((float) $item->unit_price, 2, '.', ''),
            'discount' => number_format((float) ($item->discount_amount ?? 0), 2, '.', ''),
            'line_total' => number_format((float) ($item->line_total ?? $item->total ?? 0), 2, '.', ''),
        ])->all();

        $meta = is_array($payment->metadata) ? $payment->metadata : [];
        $methodCode = strtoupper((string) ($meta['payment_method_code'] ?? $payment->reference ?? $payment->method?->value ?? 'UNKNOWN'));
        $received = $amountReceived ?? ($meta['amount_received'] ?? null);
        $changeVal = $change ?? ($meta['change'] ?? null);

        return [
            'version' => 1,
            'branding' => $branding,
            'store' => [
                'id' => $store->id,
                'code' => $store->code,
                'name' => $store->name,
                'logo_path' => $store->logo_path,
                'theme_color' => $store->theme_color,
                'address' => $branding['address'],
                'phone' => $branding['phone'],
                'tax_number' => $branding['tax_number'],
            ],
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'currency' => $order->currency ?? 'TZS',
                'subtotal' => number_format((float) $order->subtotal, 2, '.', ''),
                'discount_total' => number_format((float) $order->discount_amount, 2, '.', ''),
                'tax_total' => number_format((float) ($order->tax_amount ?? 0), 2, '.', ''),
                'grand_total' => number_format((float) $order->total, 2, '.', ''),
            ],
            'session' => [
                'id' => $session->id,
                'terminal_id' => $session->terminal_id,
                'terminal_code' => $session->terminal?->code,
                'terminal_name' => $session->terminal?->name,
            ],
            'cashier' => [
                'id' => $cashier->id,
                'name' => $cashier->name,
            ],
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'is_walk_in' => false,
            ] : [
                'id' => null,
                'name' => 'Walk-in Customer',
                'email' => null,
                'is_walk_in' => true,
            ],
            'payment' => [
                'method_code' => $methodCode,
                'method_label' => $methodCode,
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'amount_received' => $received !== null ? number_format((float) $received, 2, '.', '') : null,
                'change' => $changeVal !== null ? number_format((float) $changeVal, 2, '.', '') : null,
                'reference' => $payment->transaction_id ?? $payment->gateway_reference ?? $payment->reference,
            ],
            'promotion' => $promotion,
            'lines' => $lines,
            'messages' => [
                'thank_you' => $branding['thank_you_message'],
                'footer' => $branding['footer_message'],
                'return_policy' => $branding['return_policy'],
                'exchange_policy' => $branding['exchange_policy'],
                'website' => $branding['website'],
                'social_media' => $branding['social_media'],
            ],
            'issued_at' => optional($issuedAt)->toIso8601String() ?? now()->toIso8601String(),
            // Future channels: email / whatsapp / sms link use the same payload.
            'delivery_channels' => [
                'print' => true,
                'email' => false,
                'whatsapp' => false,
                'sms' => false,
            ],
        ];
    }

    /**
     * Future QR verification stub — does not implement verification services.
     *
     * @return array{type: string, receipt_id: string, receipt_number: string, order_id: string, payload: string, url: null}
     */
    public function qrPayload(PosReceipt $receipt): array
    {
        return [
            'type' => 'pos_receipt',
            'receipt_id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'order_id' => $receipt->order_id,
            'payload' => 'pos-receipt:'.$receipt->id,
            // Reserved for future deep link to receipt / order / tracking page.
            'url' => null,
        ];
    }
}
