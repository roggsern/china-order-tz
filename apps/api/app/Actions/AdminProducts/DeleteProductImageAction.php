<?php

namespace App\Actions\AdminProducts;

use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;

class DeleteProductImageAction
{
    public function handle(ProductImage $image): void
    {
        if ($image->path && Storage::disk('public')->exists($image->path)) {
            Storage::disk('public')->delete($image->path);
        }

        $image->forceDelete();
    }
}
