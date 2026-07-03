<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminProducts\DeleteProductImageAction;
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
}
