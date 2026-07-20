<?php

namespace Database\Seeders;

use App\Enums\CheckoutSessionStatus;
use App\Models\CheckoutSession;
use App\Models\Order;
use App\Services\Orders\OrderEngine;
use Illuminate\Database\Seeder;

/**
 * Demo orders from validated checkout sessions (Order Engine).
 */
class OrderEngineSeeder extends Seeder
{
    public function run(): void
    {
        /** @var OrderEngine $engine */
        $engine = app(OrderEngine::class);

        $sessions = CheckoutSession::query()
            ->where('status', CheckoutSessionStatus::Validated)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('user')
            ->limit(5)
            ->get();

        if ($sessions->isEmpty()) {
            $this->command?->warn('OrderEngineSeeder skipped: no validated checkout sessions.');

            return;
        }

        foreach ($sessions as $session) {
            if ($session->user === null) {
                continue;
            }

            if (Order::query()->where('checkout_session_id', $session->id)->exists()) {
                continue;
            }

            try {
                $engine->createFromCheckoutSession($session->user, $session);
            } catch (\Throwable $e) {
                $this->command?->warn("OrderEngineSeeder skipped session {$session->id}: {$e->getMessage()}");
            }
        }
    }
}
