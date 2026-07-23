<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GrowthCampaignType;
use App\Enums\GrowthJourneyTrigger;
use App\Http\Controllers\Controller;
use App\Http\Resources\GrowthCampaignResource;
use App\Http\Resources\GrowthJourneyResource;
use App\Http\Resources\GrowthSegmentResource;
use App\Models\Admin;
use App\Models\GrowthCampaign;
use App\Models\GrowthJourney;
use App\Models\GrowthSegment;
use App\Services\Growth\GrowthEngine;
use App\Services\Stores\ActiveStoreContext;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AdminGrowthController extends Controller
{
    public function __construct(
        private readonly GrowthEngine $growth,
        private readonly ActiveStoreContext $stores,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::GROWTH_VIEW);

        /** @var Admin $admin */
        $admin = $request->user();
        $storeId = $request->query('store_id');
        if ($storeId) {
            $this->stores->resolveActiveStore($admin, $storeId);
        }

        return response()->json([
            'success' => true,
            'data' => $this->growth->dashboard($storeId),
        ]);
    }

    public function segments(Request $request): AnonymousResourceCollection
    {
        $this->authorize(AdminPermissions::GROWTH_VIEW);

        return GrowthSegmentResource::collection($this->growth->paginateSegments(
            min(max((int) $request->query('per_page', 20), 1), 100)
        ));
    }

    public function storeSegment(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::GROWTH_MANAGE);

        /** @var Admin $admin */
        $admin = $request->user();
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'rules' => ['required', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'store_id' => ['nullable', 'uuid', 'exists:stores,id'],
        ]);
        if (! empty($data['store_id'])) {
            $this->stores->resolveActiveStore($admin, $data['store_id']);
        }

        $segment = $this->growth->segments()->create($data, $admin);

        return response()->json([
            'success' => true,
            'data' => new GrowthSegmentResource($segment),
        ], 201);
    }

    public function updateSegment(GrowthSegment $segment, Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::GROWTH_MANAGE);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'rules' => ['sometimes', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'store_id' => ['nullable', 'uuid', 'exists:stores,id'],
        ]);

        return response()->json([
            'success' => true,
            'data' => new GrowthSegmentResource($this->growth->segments()->update($segment, $data)),
        ]);
    }

    public function refreshSegment(GrowthSegment $segment): JsonResponse
    {
        $this->authorize(AdminPermissions::GROWTH_MANAGE);

        $count = $this->growth->segments()->refreshMembers($segment);

        return response()->json([
            'success' => true,
            'message' => "Segment refreshed ({$count} members).",
            'data' => new GrowthSegmentResource($segment->fresh()),
        ]);
    }

    public function campaigns(Request $request): AnonymousResourceCollection
    {
        $this->authorize(AdminPermissions::GROWTH_VIEW);

        return GrowthCampaignResource::collection(
            $this->growth->paginateCampaigns($request->only(['status', 'store_id']), 20)
        );
    }

    public function storeCampaign(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::GROWTH_MANAGE);

        /** @var Admin $admin */
        $admin = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'campaign_type' => ['required', Rule::enum(GrowthCampaignType::class)],
            'status' => ['sometimes', 'string'],
            'growth_segment_id' => ['nullable', 'uuid', 'exists:growth_segments,id'],
            'store_id' => ['nullable', 'uuid', 'exists:stores,id'],
            'channel' => ['sometimes', 'string'],
            'channels' => ['nullable', 'array'],
            'message_title' => ['nullable', 'string', 'max:200'],
            'message_body' => ['required', 'string'],
            'scheduled_at' => ['nullable', 'date'],
            'bonus_points' => ['nullable', 'integer', 'min:1'],
            'create_promotion' => ['sometimes', 'boolean'],
            'promotion' => ['nullable', 'array'],
        ]);

        $campaign = $this->growth->campaigns()->create($data, $admin);

        return response()->json([
            'success' => true,
            'data' => new GrowthCampaignResource($campaign),
        ], 201);
    }

    public function showCampaign(GrowthCampaign $campaign): JsonResponse
    {
        $this->authorize(AdminPermissions::GROWTH_VIEW);

        return response()->json([
            'success' => true,
            'data' => new GrowthCampaignResource($campaign->load(['segment', 'store', 'promotion'])),
        ]);
    }

    public function updateCampaign(GrowthCampaign $campaign, Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::GROWTH_MANAGE);

        /** @var Admin $admin */
        $admin = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'campaign_type' => ['sometimes', Rule::enum(GrowthCampaignType::class)],
            'status' => ['sometimes', 'string'],
            'growth_segment_id' => ['nullable', 'uuid', 'exists:growth_segments,id'],
            'store_id' => ['nullable', 'uuid', 'exists:stores,id'],
            'channel' => ['sometimes', 'string'],
            'channels' => ['nullable', 'array'],
            'message_title' => ['nullable', 'string'],
            'message_body' => ['sometimes', 'string'],
            'bonus_points' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json([
            'success' => true,
            'data' => new GrowthCampaignResource($this->growth->campaigns()->update($campaign, $data, $admin)),
        ]);
    }

    public function sendCampaign(GrowthCampaign $campaign): JsonResponse
    {
        $this->authorize(AdminPermissions::GROWTH_MANAGE);

        /** @var Admin $admin */
        $admin = request()->user();
        $sent = $this->growth->campaigns()->send($campaign, $admin);

        return response()->json([
            'success' => true,
            'message' => 'Campaign sent.',
            'data' => new GrowthCampaignResource($sent),
        ]);
    }

    public function campaignAnalytics(GrowthCampaign $campaign): JsonResponse
    {
        $this->authorize(AdminPermissions::GROWTH_VIEW);

        return response()->json([
            'success' => true,
            'data' => [
                'campaign' => new GrowthCampaignResource($campaign),
                'analytics' => $this->growth->campaigns()->analytics($campaign),
            ],
        ]);
    }

    public function journeys(Request $request): AnonymousResourceCollection
    {
        $this->authorize(AdminPermissions::GROWTH_VIEW);

        return GrowthJourneyResource::collection($this->growth->paginateJourneys(
            min(max((int) $request->query('per_page', 20), 1), 100)
        ));
    }

    public function storeJourney(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::GROWTH_MANAGE);

        /** @var Admin $admin */
        $admin = $request->user();
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'trigger_type' => ['required', Rule::enum(GrowthJourneyTrigger::class)],
            'trigger_config' => ['nullable', 'array'],
            'growth_segment_id' => ['nullable', 'uuid', 'exists:growth_segments,id'],
            'growth_campaign_id' => ['nullable', 'uuid', 'exists:growth_campaigns,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'data' => new GrowthJourneyResource($this->growth->journeys()->create($data, $admin)),
        ], 201);
    }

    public function runJourneys(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::GROWTH_MANAGE);

        /** @var Admin $admin */
        $admin = $request->user();
        $data = $request->validate([
            'send_campaigns' => ['sometimes', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->growth->journeys()->runTriggers($admin, (bool) ($data['send_campaigns'] ?? false)),
        ]);
    }
}
