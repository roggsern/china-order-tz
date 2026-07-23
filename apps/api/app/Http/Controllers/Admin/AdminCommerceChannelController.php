<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommerceChannelResource;
use App\Models\CommerceChannel;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminCommerceChannelController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize(AdminPermissions::CONFIGURATION_VIEW);

        $channels = CommerceChannel::query()
            ->orderBy('name')
            ->get();

        return CommerceChannelResource::collection($channels)
            ->additional(['success' => true]);
    }

    public function show(CommerceChannel $channel): JsonResponse
    {
        $this->authorize(AdminPermissions::CONFIGURATION_VIEW);

        return response()->json([
            'success' => true,
            'data' => new CommerceChannelResource($channel),
        ]);
    }
}
