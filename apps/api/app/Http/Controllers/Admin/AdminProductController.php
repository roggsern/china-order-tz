<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminProducts\GetAdminProductsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminProductController extends Controller
{
    public function index(GetAdminProductsAction $action): AnonymousResourceCollection
    {
        return ProductResource::collection($action->handle())
            ->additional(['success' => true]);
    }
}
