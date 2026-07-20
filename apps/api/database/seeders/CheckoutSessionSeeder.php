<?php

namespace Database\Seeders;

use App\Enums\CartStatus;
use App\Enums\CheckoutSessionStatus;
use App\Models\Cart;
use App\Models\CheckoutSession;
use App\Models\User;
use App\Services\Checkout\CheckoutOrchestrator;
use Illuminate\Database\Seeder;

/**
 * Demo checkout sessions for Checkout Orchestrator.
 * Uses existing carts with variant-backed lines only.
 */
class CheckoutSessionSeeder extends Seeder
{
    public function run(): void
    {
        /** @var CheckoutOrchestrator $orchestrator */
        $orchestrator = app(CheckoutOrchestrator::class);

        $carts = Cart::query()
            ->where('status', CartStatus::Active)
            ->whereHas('items', fn ($q) => $q->whereNotNull('product_variant_id'))
            ->with(['items', 'user'])
            ->limit(3)
            ->get();

        if ($carts->isEmpty()) {
            $this->command?->warn('CheckoutSessionSeeder skipped: no active carts with variants.');

            return;
        }

        foreach ($carts as $cart) {
            /** @var User|null $user */
            $user = $cart->user;
            if ($user === null) {
                continue;
            }

            $existing = CheckoutSession::query()
                ->where('user_id', $user->id)
                ->where('cart_id', $cart->id)
                ->whereIn('status', [
                    CheckoutSessionStatus::Draft->value,
                    CheckoutSessionStatus::Validated->value,
                ])
                ->exists();

            if ($existing) {
                continue;
            }

            try {
                $orchestrator->start($user);
            } catch (\Throwable $e) {
                $this->command?->warn("CheckoutSessionSeeder skipped cart {$cart->id}: {$e->getMessage()}");
            }
        }
    }
}
