<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CMS\ActivateCmsCampaignAction;
use App\Actions\CMS\ArchiveCmsCampaignAction;
use App\Actions\CMS\CreateCmsCampaignAction;
use App\Actions\CMS\UpdateCmsCampaignAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CMS\StoreCmsCampaignRequest;
use App\Http\Requests\Admin\CMS\UpdateCmsCampaignRequest;
use App\Http\Resources\CmsCampaignResource;
use App\Models\Admin;
use App\Models\CmsCampaign;
use App\Services\CMS\CmsCampaignService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminCmsCampaignController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CmsCampaignService $campaigns) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CmsCampaign::class);
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        return CmsCampaignResource::collection(
            $this->campaigns->paginate(
                $request->only(['status', 'commerce_context', 'search']),
                $perPage,
            ),
        )->additional(['success' => true]);
    }

    public function store(StoreCmsCampaignRequest $request, CreateCmsCampaignAction $action): JsonResponse
    {
        $campaign = $action->handle($request->validated(), $this->admin());

        return response()->json([
            'success' => true,
            'data' => new CmsCampaignResource($campaign),
        ], 201);
    }

    public function show(CmsCampaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        return response()->json([
            'success' => true,
            'data' => new CmsCampaignResource($this->campaigns->show($campaign)),
        ]);
    }

    public function update(
        CmsCampaign $campaign,
        UpdateCmsCampaignRequest $request,
        UpdateCmsCampaignAction $action,
    ): JsonResponse {
        $updated = $action->handle($campaign, $request->validated(), $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Campaign updated.',
            'data' => new CmsCampaignResource($updated),
        ]);
    }

    public function activate(CmsCampaign $campaign, ActivateCmsCampaignAction $action): JsonResponse
    {
        $this->authorize('publish', $campaign);
        $updated = $action->handle($campaign, $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Campaign activated.',
            'data' => new CmsCampaignResource($updated),
        ]);
    }

    public function archive(CmsCampaign $campaign, ArchiveCmsCampaignAction $action): JsonResponse
    {
        $this->authorize('archive', $campaign);
        $updated = $action->handle($campaign, $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Campaign archived.',
            'data' => new CmsCampaignResource($updated),
        ]);
    }

    public function updatePriority(CmsCampaign $campaign, Request $request): JsonResponse
    {
        $this->authorize('update', $campaign);
        $validated = $request->validate([
            'priority' => ['required', 'integer', 'min:0'],
        ]);
        $updated = $this->campaigns->updatePriority($campaign, (int) $validated['priority'], $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Campaign priority updated.',
            'data' => new CmsCampaignResource($updated),
        ]);
    }

    public function attachLayout(CmsCampaign $campaign, Request $request): JsonResponse
    {
        $this->authorize('update', $campaign);
        $validated = $request->validate([
            'cms_homepage_layout_id' => ['required', 'uuid', 'exists:cms_homepage_layouts,id'],
        ]);
        $updated = $this->campaigns->attachLayout(
            $campaign,
            $validated['cms_homepage_layout_id'],
            $this->admin(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Homepage layout attached.',
            'data' => new CmsCampaignResource($updated),
        ]);
    }

    public function attachHeroSlides(CmsCampaign $campaign, Request $request): JsonResponse
    {
        $this->authorize('update', $campaign);
        $validated = $request->validate([
            'hero_slide_ids' => ['required', 'array'],
            'hero_slide_ids.*' => ['uuid', 'distinct'],
        ]);
        $updated = $this->campaigns->attachHeroSlides(
            $campaign,
            $validated['hero_slide_ids'],
            $this->admin(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Hero slides attached.',
            'data' => new CmsCampaignResource($this->campaigns->show($updated)),
        ]);
    }

    public function attachFeaturedContents(CmsCampaign $campaign, Request $request): JsonResponse
    {
        $this->authorize('update', $campaign);
        $validated = $request->validate([
            'featured_content_ids' => ['required', 'array'],
            'featured_content_ids.*' => ['uuid', 'distinct'],
        ]);
        $updated = $this->campaigns->attachFeaturedContents(
            $campaign,
            $validated['featured_content_ids'],
            $this->admin(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Featured contents attached.',
            'data' => new CmsCampaignResource($this->campaigns->show($updated)),
        ]);
    }

    public function attachPromotions(CmsCampaign $campaign, Request $request): JsonResponse
    {
        $this->authorize('update', $campaign);
        $validated = $request->validate([
            'promotion_ids' => ['required', 'array'],
            'promotion_ids.*' => ['uuid', 'distinct', 'exists:promotions,id'],
        ]);
        $updated = $this->campaigns->attachPromotions(
            $campaign,
            $validated['promotion_ids'],
            $this->admin(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Promotions attached.',
            'data' => new CmsCampaignResource($this->campaigns->show($updated)),
        ]);
    }

    public function attachNavigationShells(CmsCampaign $campaign, Request $request): JsonResponse
    {
        $this->authorize('update', $campaign);
        $validated = $request->validate([
            'navigation_shell_ids' => ['required', 'array'],
            'navigation_shell_ids.*' => ['uuid', 'distinct'],
        ]);
        $updated = $this->campaigns->attachNavigationShells(
            $campaign,
            $validated['navigation_shell_ids'],
            $this->admin(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Navigation shells attached.',
            'data' => new CmsCampaignResource($this->campaigns->show($updated)),
        ]);
    }

    private function admin(): ?Admin
    {
        $user = auth('sanctum')->user();

        return $user instanceof Admin ? $user : null;
    }
}
