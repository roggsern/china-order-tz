<?php

namespace App\Actions\AdminProducts;

use App\Models\Product;
use App\Services\AdminProducts\ProductForceDeleteEligibilityService;
use Illuminate\Support\Facades\Log;

class GetProductForceDeleteEligibilityAction
{
    public function __construct(
        private readonly ProductForceDeleteEligibilityService $eligibility,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(string $id, ?string $actorAdminId = null): array
    {
        $product = Product::onlyTrashed()->findOrFail($id);
        $evaluation = $this->eligibility->evaluate($product);

        Log::info('product_force_delete_eligibility_checked', [
            'actor_admin_id' => $actorAdminId,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'can_force_delete' => $evaluation['can_force_delete'],
            'blocking_dependency_types' => array_column($evaluation['blocking_dependencies'], 'type'),
        ]);

        return $evaluation;
    }
}
