<?php

namespace App\Actions\AdminProducts;

use App\Models\Product;
use App\Services\AdminProducts\ProductDeletionLifecycle;
use Illuminate\Support\Facades\Log;

class RestoreProductAction
{
    public function __construct(
        private readonly ProductDeletionLifecycle $lifecycle,
    ) {}

    public function handle(string $id, ?string $actorAdminId = null): Product
    {
        $product = Product::onlyTrashed()->findOrFail($id);

        $restored = $this->lifecycle->restore($product);

        Log::info('product_restored', [
            'actor_admin_id' => $actorAdminId,
            'product_id' => $restored->id,
            'product_name' => $restored->name,
            'product_slug' => $restored->slug,
        ]);

        return $restored;
    }
}
