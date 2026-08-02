<?php

namespace App\Actions\AdminProducts;

use App\Models\Product;
use App\Services\Catalog\CustomerProductMediaResolver;

class GetProductImagesAction
{
    public function __construct(
        private readonly CustomerProductMediaResolver $mediaResolver,
    ) {}

    /**
     * @return list<array{
     *     id: string,
     *     path: string|null,
     *     url: string|null,
     *     thumbnail_url: string|null,
     *     alt_text: string|null,
     *     sort_order: int,
     *     is_primary: bool
     * }>
     */
    public function handle(Product $product): array
    {
        $product->loadMissing(CustomerProductMediaResolver::adminProductEagerLoads());

        return $this->mediaResolver->resolveAdminGallery($product);
    }
}
