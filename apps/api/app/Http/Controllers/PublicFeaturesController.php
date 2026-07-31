<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Features\FeatureAvailabilityService;
use Illuminate\Http\JsonResponse;

class PublicFeaturesController extends Controller
{
    public function __construct(
        private readonly FeatureAvailabilityService $features,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->features->publicFlags(),
        ]);
    }
}
