<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminProducts\DeleteProductImageAction;
use App\Actions\AdminProducts\SetPrimaryProductImageAction;
use App\Http\Controllers\Controller;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;

class AdminProductImageController extends Controller
{
    public function destroy(ProductImage $image, DeleteProductImageAction $action): JsonResponse
    {
        $action->handle($image);

        return response()->json([
            'success' => true,
            'message' => 'Image deleted successfully',
        ]);
    }

    public function setPrimary(ProductImage $image, SetPrimaryProductImageAction $action): JsonResponse
    {
        $action->handle($image);

        return response()->json([
            'success' => true,
            'message' => 'Primary image updated successfully',
        ]);
    }
}
