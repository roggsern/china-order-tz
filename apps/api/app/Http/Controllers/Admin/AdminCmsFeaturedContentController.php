<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CMS\CreateFeaturedContentAction;
use App\Actions\CMS\DeleteFeaturedContentAction;
use App\Actions\CMS\ReorderFeaturedContentsAction;
use App\Actions\CMS\ToggleFeaturedContentVisibilityAction;
use App\Actions\CMS\UpdateFeaturedContentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CMS\ReorderCmsFeaturedContentsRequest;
use App\Http\Requests\Admin\CMS\StoreCmsFeaturedContentRequest;
use App\Http\Requests\Admin\CMS\UpdateCmsFeaturedContentRequest;
use App\Http\Resources\CmsFeaturedContentResource;
use App\Models\Admin;
use App\Models\CmsFeaturedContent;
use App\Models\CmsHomepageLayout;
use App\Models\CmsHomepageSection;
use App\Services\CMS\CmsFeaturedContentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminCmsFeaturedContentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CmsFeaturedContentService $featured) {}

    public function index(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
    ): AnonymousResourceCollection {
        $this->assertSectionBelongsToLayout($layout, $section);
        $this->authorize('viewAny', CmsFeaturedContent::class);

        return CmsFeaturedContentResource::collection(
            $this->featured->listForSection($section),
        )->additional(['success' => true]);
    }

    public function store(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
        StoreCmsFeaturedContentRequest $request,
        CreateFeaturedContentAction $action,
    ): JsonResponse {
        $this->assertSectionBelongsToLayout($layout, $section);
        $created = $action->handle($section, $request->validated(), $this->admin());

        return response()->json([
            'success' => true,
            'data' => new CmsFeaturedContentResource($created),
        ], 201);
    }

    public function show(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
        CmsFeaturedContent $featuredContent,
    ): JsonResponse {
        $this->assertNestedOwnership($layout, $section, $featuredContent);
        $this->authorize('view', $featuredContent);

        return response()->json([
            'success' => true,
            'data' => new CmsFeaturedContentResource($this->featured->show($featuredContent)),
        ]);
    }

    public function update(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
        CmsFeaturedContent $featuredContent,
        UpdateCmsFeaturedContentRequest $request,
        UpdateFeaturedContentAction $action,
    ): JsonResponse {
        $this->assertNestedOwnership($layout, $section, $featuredContent);
        $updated = $action->handle($featuredContent, $request->validated(), $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Featured content updated.',
            'data' => new CmsFeaturedContentResource($updated),
        ]);
    }

    public function toggleVisibility(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
        CmsFeaturedContent $featuredContent,
        ToggleFeaturedContentVisibilityAction $action,
    ): JsonResponse {
        $this->assertNestedOwnership($layout, $section, $featuredContent);
        $this->authorize('update', $featuredContent);
        $updated = $action->handle($featuredContent, $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Featured content visibility toggled.',
            'data' => new CmsFeaturedContentResource($updated),
        ]);
    }

    public function reorder(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
        ReorderCmsFeaturedContentsRequest $request,
        ReorderFeaturedContentsAction $action,
    ): JsonResponse {
        $this->assertSectionBelongsToLayout($layout, $section);
        $items = $action->handle($section, $request->validated(), $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Featured contents reordered.',
            'data' => CmsFeaturedContentResource::collection($items),
        ]);
    }

    public function destroy(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
        CmsFeaturedContent $featuredContent,
        DeleteFeaturedContentAction $action,
    ): JsonResponse {
        $this->assertNestedOwnership($layout, $section, $featuredContent);
        $this->authorize('delete', $featuredContent);
        $action->handle($featuredContent, $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Featured content deleted.',
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
        abort_unless($section->cms_homepage_layout_id === $layout->id, 404);
    }

    private function assertNestedOwnership(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
        CmsFeaturedContent $featured,
    ): void {
        $this->assertSectionBelongsToLayout($layout, $section);
        abort_unless($featured->cms_homepage_section_id === $section->id, 404);
    }
}
