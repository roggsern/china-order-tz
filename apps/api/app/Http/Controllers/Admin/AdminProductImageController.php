<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminProducts\DeleteProductImageAction;
use App\Actions\AdminProducts\SetPrimaryProductImageAction;
use App\Http\Controllers\Controller;
use App\Models\ProductImage;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;

class AdminProductImageController extends Controller
{
    public function destroy(ProductImage $image, DeleteProductImageAction $action): JsonResponse
    {
        $this->authorize(AdminPermissions::CATALOG_UPDATE);

        $action->handle($image);

        return response()->json([
            'success' => true,
            'message' => 'Image deleted successfully',
        ]);
    }

    public function setPrimary(ProductImage $image, SetPrimaryProductImageAction $action): JsonResponse
    {
        $this->authorize(AdminPermissions::CATALOG_UPDATE);

        $action->handle($image);

        return response()->json([
            'success' => true,
            'message' => 'Primary image updated successfully',
        ]);
    }
}
