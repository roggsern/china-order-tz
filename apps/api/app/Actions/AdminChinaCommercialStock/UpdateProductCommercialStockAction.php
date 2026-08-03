<?php

namespace App\Actions\AdminChinaCommercialStock;

use App\Http\Requests\Admin\UpdateProductCommercialStockRequest;
use App\Http\Resources\ChinaCommercialStockResource;
use App\Models\Product;
use App\Services\China\Procurement\ChinaCommercialStockService;
use App\Services\Commerce\CommerceChannelResolver;
use Illuminate\Validation\ValidationException;

final class UpdateProductCommercialStockAction
{
    public function __construct(
        private readonly ChinaCommercialStockService $commercialStock,
        private readonly CommerceChannelResolver $commerceChannels,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(UpdateProductCommercialStockRequest $request, Product $product): array
    {
        $product->loadMissing(['commerceChannel', 'variants']);

        $this->assertChinaImportProduct($product);

        if ($product->variants->isNotEmpty()) {
            throw ValidationException::withMessages([
                'variants' => ['Simple commercial stock applies only to products without variants. Manage availability per variant instead.'],
            ]);
        }

        $availableQuantity = (int) $request->validated('available_quantity');
        $row = $this->commercialStock->setAvailable($product, $availableQuantity, null);

        return (new ChinaCommercialStockResource($row))->resolve();
    }

    private function assertChinaImportProduct(Product $product): void
    {
        if ($this->commerceChannels->isChinaImportProduct($product)) {
            return;
        }

        throw ValidationException::withMessages([
            'commerce_channel' => ['Commercial stock is only available for CHINA_IMPORT products.'],
        ]);
    }
}
