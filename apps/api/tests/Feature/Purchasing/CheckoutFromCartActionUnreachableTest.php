<?php

namespace Tests\Feature\Purchasing;

use App\Actions\Cart\CheckoutFromCartAction;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Architectural lock: CheckoutFromCartAction must stay unused.
 * If it is ever routed, it must go through AssertPurchaseQuantity /
 * CheckoutOrchestrator — it currently creates orders without that check.
 */
class CheckoutFromCartActionUnreachableTest extends TestCase
{
    public function test_checkout_from_cart_action_is_not_bound_to_http_routes(): void
    {
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            $this->assertStringNotContainsString(
                CheckoutFromCartAction::class,
                $action,
                'CheckoutFromCartAction must not be reachable over HTTP.',
            );
        }
    }

    public function test_checkout_from_cart_action_is_not_referenced_from_http_or_order_engine(): void
    {
        $roots = [
            app_path('Http'),
            app_path('Services/Orders'),
            app_path('Services/Checkout'),
            base_path('routes'),
        ];

        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = (string) file_get_contents($file->getPathname());
                $this->assertStringNotContainsString(
                    'CheckoutFromCartAction',
                    $contents,
                    $file->getPathname().' must not reference the dead checkout path.',
                );
            }
        }
    }
}
