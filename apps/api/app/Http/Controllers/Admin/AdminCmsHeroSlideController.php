<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CMS\ActivateHeroSlideAction;
use App\Actions\CMS\ArchiveHeroSlideAction;
use App\Actions\CMS\CreateHeroSlideAction;
use App\Actions\CMS\DeleteHeroSlideAction;
use App\Actions\CMS\ReorderHeroSlidesAction;
use App\Actions\CMS\ToggleHeroSlideVisibilityAction;
use App\Actions\CMS\UpdateHeroSlideAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CMS\ReorderCmsHeroSlidesRequest;
use App\Http\Requests\Admin\CMS\StoreCmsHeroSlideRequest;
use App\Http\Requests\Admin\CMS\UpdateCmsHeroSlideRequest;
use App\Http\Resources\CmsHeroSlideResource;
use App\Models\Admin;
use App\Models\CmsHeroSlide;
use App\Models\CmsHomepageLayout;
use App\Models\CmsHomepageSection;
use App\Services\CMS\CmsHeroSlideService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminCmsHeroSlideController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CmsHeroSlideService $heroes) {}

    public function index(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
    ): AnonymousResourceCollection {
        $this->assertSectionBelongsToLayout($layout, $section);
        $this->authorize('viewAny', CmsHeroSlide::class);

        return CmsHeroSlideResource::collection(
            $this->heroes->listForSection($section),
        )->additional(['success' => true]);
    }

    public function store(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
        StoreCmsHeroSlideRequest $request,
        CreateHeroSlideAction $action,
    ): JsonResponse {
        $this->assertSectionBelongsToLayout($layout, $section);
        $slide = $action->handle($section, $request->validated(), $this->admin());

        return response()->json([
            'success' => true,
            'data' => new CmsHeroSlideResource($slide),
        ], 201);
    }

    public function show(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
        CmsHeroSlide $heroSlide,
    ): JsonResponse {
        $this->assertNestedOwnership($layout, $section, $heroSlide);
        $this->authorize('view', $heroSlide);

        return response()->json([
            'success' => true,
            'data' => new CmsHeroSlideResource($this->heroes->show($heroSlide)),
        ]);
    }

    public function update(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
        CmsHeroSlide $heroSlide,
        UpdateCmsHeroSlideRequest $request,
        UpdateHeroSlideAction $action,
    ): JsonResponse {
        $this->assertNestedOwnership($layout, $section, $heroSlide);
        $updated = $action->handle($heroSlide, $request->validated(), $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Hero slide updated.',
            'data' => new CmsHeroSlideResource($updated),
        ]);
    }

    public function activate(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
        CmsHeroSlide $heroSlide,
        ActivateHeroSlideAction $action,
    ): JsonResponse {
        $this->assertNestedOwnership($layout, $section, $heroSlide);
        $this->authorize('publish', $heroSlide);
        $updated = $action->handle($heroSlide, $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Hero slide activated.',
            'data' => new CmsHeroSlideResource($updated),
        ]);
    }

    public function archive(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
        CmsHeroSlide $heroSlide,
        ArchiveHeroSlideAction $action,
    ): JsonResponse {
        $this->assertNestedOwnership($layout, $section, $heroSlide);
        $this->authorize('archive', $heroSlide);
        $updated = $action->handle($heroSlide, $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Hero slide archived.',
            'data' => new CmsHeroSlideResource($updated),
        ]);
    }

    public function toggleVisibility(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
        CmsHeroSlide $heroSlide,
        ToggleHeroSlideVisibilityAction $action,
    ): JsonResponse {
        $this->assertNestedOwnership($layout, $section, $heroSlide);
        $this->authorize('update', $heroSlide);
        $updated = $action->handle($heroSlide, $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Hero slide visibility toggled.',
            'data' => new CmsHeroSlideResource($updated),
        ]);
    }

    public function reorder(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
        ReorderCmsHeroSlidesRequest $request,
        ReorderHeroSlidesAction $action,
    ): JsonResponse {
        $this->assertSectionBelongsToLayout($layout, $section);
        $slides = $action->handle($section, $request->validated(), $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Hero slides reordered.',
            'data' => CmsHeroSlideResource::collection($slides),
        ]);
    }

    public function destroy(
        CmsHomepageLayout $layout,
        CmsHomepageSection $section,
        CmsHeroSlide $heroSlide,
        DeleteHeroSlideAction $action,
    ): JsonResponse {
        $this->assertNestedOwnership($layout, $section, $heroSlide);
        $this->authorize('delete', $heroSlide);
        $action->handle($heroSlide, $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Hero slide deleted.',
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
        CmsHeroSlide $slide,
    ): void {
        $this->assertSectionBelongsToLayout($layout, $section);
        abort_unless($slide->cms_homepage_section_id === $section->id, 404);
    }
}
