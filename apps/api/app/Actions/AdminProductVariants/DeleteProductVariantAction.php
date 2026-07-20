<?php

namespace App\Actions\AdminProductVariants;

use App\Actions\AdminProductVariants\Concerns\ResolvesVariantDefaults;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteProductVariantAction
{
    use ResolvesVariantDefaults;

    public function handle(Product $product, ProductVariant $variant): void
    {
        if ($variant->product_id !== $product->id) {
            throw ValidationException::withMessages([
                'variant' => ['Variant does not belong to this product.'],
            ]);
        }

        DB::transaction(function () use ($product, $variant) {
            $variant->catalogAttributeValues()->delete();
            $variant->delete();
            $this->ensureSingleDefault($product);
        });
    }
}
