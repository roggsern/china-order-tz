<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CMS\ArchiveCmsNavigationShellAction;
use App\Actions\CMS\CreateCmsNavigationItemAction;
use App\Actions\CMS\CreateCmsNavigationShellAction;
use App\Actions\CMS\DeleteCmsNavigationShellAction;
use App\Actions\CMS\PublishCmsNavigationShellAction;
use App\Actions\CMS\ReorderCmsNavigationItemsAction;
use App\Actions\CMS\UpdateCmsNavigationItemAction;
use App\Actions\CMS\UpdateCmsNavigationShellAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CMS\ReorderCmsNavigationItemsRequest;
use App\Http\Requests\Admin\CMS\StoreCmsNavigationItemRequest;
use App\Http\Requests\Admin\CMS\StoreCmsNavigationShellRequest;
use App\Http\Requests\Admin\CMS\UpdateCmsNavigationItemRequest;
use App\Http\Requests\Admin\CMS\UpdateCmsNavigationShellRequest;
use App\Http\Resources\CmsNavigationItemResource;
use App\Http\Resources\CmsNavigationShellResource;
use App\Models\Admin;
use App\Models\CmsNavigationItem;
use App\Models\CmsNavigationShell;
use App\Services\CMS\CmsNavigationShellService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminCmsNavigationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CmsNavigationShellService $shells) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CmsNavigationShell::class);
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        return CmsNavigationShellResource::collection(
            $this->shells->paginate(
                $request->only(['status', 'commerce_context', 'navigation_type', 'search']),
                $perPage,
            ),
        )->additional(['success' => true]);
    }

    public function store(StoreCmsNavigationShellRequest $request, CreateCmsNavigationShellAction $action): JsonResponse
    {
        $shell = $action->handle($request->validated(), $this->admin());

        return response()->json([
            'success' => true,
            'data' => new CmsNavigationShellResource($shell),
        ], 201);
    }

    public function show(CmsNavigationShell $navigationShell): JsonResponse
    {
        $this->authorize('view', $navigationShell);

        return response()->json([
            'success' => true,
            'data' => new CmsNavigationShellResource($this->shells->show($navigationShell)),
        ]);
    }

    public function update(
        CmsNavigationShell $navigationShell,
        UpdateCmsNavigationShellRequest $request,
        UpdateCmsNavigationShellAction $action,
    ): JsonResponse {
        $updated = $action->handle($navigationShell, $request->validated(), $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Navigation shell updated.',
            'data' => new CmsNavigationShellResource($updated),
        ]);
    }

    public function publish(
        CmsNavigationShell $navigationShell,
        PublishCmsNavigationShellAction $action,
    ): JsonResponse {
        $this->authorize('publish', $navigationShell);
        $updated = $action->handle($navigationShell, $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Navigation shell published.',
            'data' => new CmsNavigationShellResource($updated),
        ]);
    }

    public function archive(
        CmsNavigationShell $navigationShell,
        ArchiveCmsNavigationShellAction $action,
    ): JsonResponse {
        $this->authorize('update', $navigationShell);
        $updated = $action->handle($navigationShell, $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Navigation shell archived.',
            'data' => new CmsNavigationShellResource($updated),
        ]);
    }

    public function setDefault(CmsNavigationShell $navigationShell): JsonResponse
    {
        $this->authorize('publish', $navigationShell);
        $updated = $this->shells->setDefault($navigationShell, $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Navigation shell set as default.',
            'data' => new CmsNavigationShellResource($updated),
        ]);
    }

    public function destroy(
        CmsNavigationShell $navigationShell,
        DeleteCmsNavigationShellAction $action,
    ): JsonResponse {
        $this->authorize('delete', $navigationShell);
        $action->handle($navigationShell, $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Navigation shell deleted.',
        ]);
    }

    public function items(CmsNavigationShell $navigationShell): JsonResponse
    {
        $this->authorize('view', $navigationShell);
        $shell = $this->shells->show($navigationShell);

        return response()->json([
            'success' => true,
            'data' => CmsNavigationItemResource::collection($shell->items),
        ]);
    }

    public function storeItem(
        CmsNavigationShell $navigationShell,
        StoreCmsNavigationItemRequest $request,
        CreateCmsNavigationItemAction $action,
    ): JsonResponse {
        $item = $action->handle($navigationShell, $request->validated(), $this->admin());

        return response()->json([
            'success' => true,
            'data' => new CmsNavigationItemResource($item),
        ], 201);
    }

    public function updateItem(
        CmsNavigationShell $navigationShell,
        CmsNavigationItem $item,
        UpdateCmsNavigationItemRequest $request,
        UpdateCmsNavigationItemAction $action,
    ): JsonResponse {
        $this->assertNestedItem($navigationShell, $item);
        $updated = $action->handle($navigationShell, $item, $request->validated(), $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Navigation item updated.',
            'data' => new CmsNavigationItemResource($updated),
        ]);
    }

    public function enableItem(CmsNavigationShell $navigationShell, CmsNavigationItem $item): JsonResponse
    {
        $this->authorize('update', $navigationShell);
        $this->assertNestedItem($navigationShell, $item);
        $updated = $this->shells->enableItem($navigationShell, $item, $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Navigation item enabled.',
            'data' => new CmsNavigationItemResource($updated),
        ]);
    }

    public function disableItem(CmsNavigationShell $navigationShell, CmsNavigationItem $item): JsonResponse
    {
        $this->authorize('update', $navigationShell);
        $this->assertNestedItem($navigationShell, $item);
        $updated = $this->shells->disableItem($navigationShell, $item, $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Navigation item disabled.',
            'data' => new CmsNavigationItemResource($updated),
        ]);
    }

    public function destroyItem(CmsNavigationShell $navigationShell, CmsNavigationItem $item): JsonResponse
    {
        $this->authorize('update', $navigationShell);
        $this->assertNestedItem($navigationShell, $item);
        $this->shells->deleteItem($navigationShell, $item, $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Navigation item deleted.',
        ]);
    }

    public function reorderItems(
        CmsNavigationShell $navigationShell,
        ReorderCmsNavigationItemsRequest $request,
        ReorderCmsNavigationItemsAction $action,
    ): JsonResponse {
        $updated = $action->handle($navigationShell, $request->validated(), $this->admin());

        return response()->json([
            'success' => true,
            'message' => 'Navigation items reordered.',
            'data' => new CmsNavigationShellResource($this->shells->show($updated)),
        ]);
    }

    private function assertNestedItem(CmsNavigationShell $shell, CmsNavigationItem $item): void
    {
        if ($item->navigation_shell_id !== $shell->id) {
            throw new NotFoundHttpException('Navigation item not found for this shell.');
        }
    }

    private function admin(): ?Admin
    {
        $user = auth('sanctum')->user();

        return $user instanceof Admin ? $user : null;
    }
}
