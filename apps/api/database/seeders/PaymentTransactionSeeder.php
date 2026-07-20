<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Database\Seeder;

/**
 * Demo payment transactions for local/dev.
 * Never calls the real NMB API.
 */
class PaymentTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $orders = Order::query()
            ->whereIn('status', [
                OrderStatus::PendingPayment->value,
                OrderStatus::Pending->value,
            ])
            ->whereDoesntHave('paymentTransactions')
            ->limit(5)
            ->get();

        if ($orders->isEmpty()) {
            $this->command?->warn('PaymentTransactionSeeder skipped: no pending payment orders.');

            return;
        }

        foreach ($orders as $order) {
            PaymentTransaction::query()->firstOrCreate(
                [
                    'order_id' => $order->id,
                    'merchant_reference' => 'COTZ-PAY-DEMO-'.$order->order_number,
                ],
                [
                    'provider' => PaymentProvider::Nmb,
                    'currency' => $order->currency ?: 'TZS',
                    'amount' => $order->total,
                    'status' => PaymentTransactionStatus::Processing,
                    'provider_reference' => 'NMB-DEMO-SESSION-'.$order->order_number,
                    'checkout_url' => null,
                    'request_payload' => ['mode' => 'seed', 'provider' => 'nmb'],
                    'response_payload' => ['mode' => 'seed', 'note' => 'Demo only — no NMB API call'],
                    'initiated_at' => now(),
                ],
            );
        }
    }
}
