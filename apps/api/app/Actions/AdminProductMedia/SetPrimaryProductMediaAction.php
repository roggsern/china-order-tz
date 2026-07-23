<?php

namespace App\Actions\AdminProductMedia;

use App\Enums\ProductMediaType;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetPrimaryProductMediaAction
{
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

        return DB::transaction(function () use ($product, $media) {
            $product->media()->images()->update(['is_primary' => false]);
            $media->update([
                'is_primary' => true,
                'is_active' => true,
            ]);

            return $media->fresh();
        });
    }
}
