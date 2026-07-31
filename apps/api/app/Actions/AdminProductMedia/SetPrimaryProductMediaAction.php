<?php

namespace App\Actions\AdminProductMedia;

use App\Enums\ProductMediaType;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\ProductMedia\ProductPrimarySyncService;
use Illuminate\Validation\ValidationException;

class SetPrimaryProductMediaAction
{
    public function __construct(
        private readonly ProductPrimarySyncService $primarySync,
    ) {}

    public function handle(Product $product, ProductMedia $media): ProductMedia
    {
        if ($media->product_id !== $product->id) {
            abort(404);
        }

        if ($media->type !== ProductMediaType::Image) {
            throw ValidationException::withMessages([
                'media' => ['Only image media can be set as primary.'],
            ]);
        }

        return $this->primarySync->setPrimaryFromCatalogMedia($media)->load('variant');
    }
}
