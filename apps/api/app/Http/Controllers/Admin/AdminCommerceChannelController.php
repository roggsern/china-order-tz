<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommerceChannelResource;
use App\Models\CommerceChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminCommerceChannelController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $channels = CommerceChannel::query()
            ->orderBy('name')
            ->get();

        return CommerceChannelResource::collection($channels)
            ->additional(['success' => true]);
    }

    public function show(CommerceChannel $channel): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new CommerceChannelResource($channel),
        ]);
    }
}
