<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CMS\ArchiveHomepageLayoutAction;
use App\Actions\CMS\CreateHomepageLayoutAction;
use App\Actions\CMS\CreateHomepageSectionAction;
use App\Actions\CMS\DeleteHomepageSectionAction;
use App\Actions\CMS\ReorderHomepageSectionsAction;
use App\Actions\CMS\SetHomepageLayoutDefaultAction;
use App\Actions\CMS\ToggleHomepageSectionVisibilityAction;
use App\Actions\CMS\UpdateHomepageLayoutAction;
use App\Actions\CMS\UpdateHomepageSectionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CMS\ReorderCmsHomepageSectionsRequest;
use App\Http\Requests\Admin\CMS\StoreCmsHomepageLayoutRequest;
use App\Http\Requests\Admin\CMS\StoreCmsHomepageSectionRequest;
use App\Http\Requests\Admin\CMS\UpdateCmsHomepageLayoutRequest;
use App\Http\Requests\Admin\CMS\UpdateCmsHomepageSectionRequest;
use App\Http\Resources\CmsHomepageLayoutResource;
use App\Http\Resources\CmsHomepageSectionResource;
use App\Models\Admin;
use App\Models\CmsHomepageLayout;
use App\Models\CmsHomepageSection;
use App\Services\CMS\CmsHomepageService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminCmsHomepageController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CmsHomepageService $cms) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CmsHomepageLayout::class);

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        return CmsHomepageLayoutResource::collection(
            $this->cms->paginateLayouts(
                $request->only(['status', 'commerce_context', 'search']),
                $perPage,
            ),
        )->additional(['success' => true]);
    }

    public function store(
        StoreCmsHomepageLayoutRequest $request,
        CreateHomepageLayoutAction $action,
    ): JsonResponse {
        $layout = $action->handle($request->validated(), $this->admin());

        return response()->json([
            'success' => true,
            'data' => new CmsHomepageLayoutResource($layout),
        ], 201);
    }

    public function show(CmsHomepageLayout $layout): JsonResponse
    {
        $this->authorize('view', $layout);

        return response()->json([
            'success' => true,
            'data' => new CmsHomepageLayoutResource($this->cms->showLayout($layout)),
        ]);
    }

    public function update(
        CmsHomepageLayout $layout,
        UpdateCmsHomepageLayoutRequest $request,
        UpdateHomepageLayoutAction $action,
    ): JsonResponse {
        $updated = $action->handle($layout, $request->validated(), $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Homepage layout updated.',
            'data' => new CmsHomepageLayoutResource($updated),
        ]);
    }

    public function setDefault(
        CmsHomepageLayout $layout,
        SetHomepageLayoutDefaultAction $action,
    ): JsonResponse {
        $this->authorize('publish', $layout);
        $updated = $action->handle($layout, $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Homepage layout set as default.',
            'data' => new CmsHomepageLayoutResource($updated),
        ]);
    }

    public function archive(
        CmsHomepageLayout $layout,
        ArchiveHomepageLayoutAction $action,
    ): JsonResponse {
        $this->authorize('archive', $layout);
        $updated = $action->handle($layout, $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Homepage layout archived.',
            'data' => new CmsHomepageLayoutResource($updated),
        ]);
    }

    public function sections(CmsHomepageLayout $layout): AnonymousResourceCollection
    {
        $this->authorize('view', $layout);

        return CmsHomepageSectionResource::collection(
            $this->cms->listSections($layout),
        )->additional(['success' => true]);
    }

    public function storeSection(
        CmsHomepageLayout $layout,
        StoreCmsHomepageSectionRequest $request,
        CreateHomepageSectionAction $action,
    ): JsonResponse {
        $section = $action->handle($layout, $request->validated(), $this->admin());

        return response()->json([
            'success' => true,
            'data' => new CmsHomepageSectionResource($section),
        ], 201);
    }

    public function updateSection(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
        UpdateCmsHomepageSectionRequest $request,
        UpdateHomepageSectionAction $action,
    ): JsonResponse {
        $this->assertSectionBelongsToLayout($layout, $section);
        $updated = $action->handle($section, $request->validated(), $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Homepage section updated.',
            'data' => new CmsHomepageSectionResource($updated),
        ]);
    }

    public function reorderSections(
        CmsHomepageLayout $layout,
        ReorderCmsHomepageSectionsRequest $request,
        ReorderHomepageSectionsAction $action,
    ): JsonResponse {
        $sections = $action->handle($layout, $request->validated(), $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Homepage sections reordered.',
            'data' => CmsHomepageSectionResource::collection($sections),
        ]);
    }

    public function toggleSectionVisibility(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
        ToggleHomepageSectionVisibilityAction $action,
    ): JsonResponse {
        $this->assertSectionBelongsToLayout($layout, $section);
        $this->authorize('update', $section);
        $updated = $action->handle($section, $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Homepage section visibility toggled.',
            'data' => new CmsHomepageSectionResource($updated),
        ]);
    }

    public function destroySection(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
        DeleteHomepageSectionAction $action,
    ): JsonResponse {
        $this->assertSectionBelongsToLayout($layout, $section);
        $this->authorize('delete', $section);
        $action->handle($section, $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Homepage section deleted.',
        ]);
    }

    private function admin(): ?Admin
    {
        $user = auth('sanctum')->user();

        return $user instanceof Admin ? $user : null;
    }

    private function assertSectionBelongsToLayout(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
    ): void {
        abort_unless(
            $section->cms_homepage_layout_id === $layout->id,
            404,
        );
    }
}
