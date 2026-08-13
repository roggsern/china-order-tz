<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\CMS\ResolveStorefrontHomepageAction;
use App\Enums\CMS\CmsCommerceContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\CmsHomepageLayoutResource;
use App\Services\Storefront\StorefrontPublicResponseCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StorefrontCmsHomepageController extends Controller
{
    public function __construct(
        private readonly StorefrontPublicResponseCache $publicCache,
    ) {}

    /**
     * Public storefront homepage for a commerce context.
     *
     * Resolution: active CmsCampaign (exact context) → campaign layout → else default layout
     * (optional GLOBAL layout fallback). Never mixes CHINA_IMPORT and TZ_LOCAL.
     */
    public function show(Request $request, ResolveStorefrontHomepageAction $action): JsonResponse
    {
        $validated = $request->validate([
            'commerce_context' => ['required', Rule::enum(CmsCommerceContext::class)],
            'allow_global_fallback' => ['sometimes', 'boolean'],
        ]);

        $context = CmsCommerceContext::from($validated['commerce_context']);
        $allowFallback = array_key_exists('allow_global_fallback', $validated)
            ? (bool) $validated['allow_global_fallback']
            : true;

        $variant = $context->value.'|fallback='.($allowFallback ? '1' : '0');

        $payload = $this->publicCache->remember(
            'cms-homepage',
            $variant,
            fn () => $this->buildHomepagePayload($action, $context, $allowFallback),
        );

        return response()->json($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHomepagePayload(
        ResolveStorefrontHomepageAction $action,
        CmsCommerceContext $context,
        bool $allowFallback,
    ): array {
        $resolved = $action->handle($context, $allowFallback);
        $layout = $resolved['layout'];
        $campaign = $resolved['campaign'];

        if ($layout === null) {
            return [
                'success' => true,
                'data' => null,
                'meta' => [
                    'commerce_context' => $context->value,
                    'allow_global_fallback' => $allowFallback,
                    'campaign' => null,
                    'message' => 'No active campaign or default homepage layout for this context.',
                ],
            ];
        }

        return [
            'success' => true,
            'data' => (new CmsHomepageLayoutResource($layout))->resolve(),
            'meta' => [
                'commerce_context' => $context->value,
                'resolved_commerce_context' => $layout->commerce_context instanceof \BackedEnum
                    ? $layout->commerce_context->value
                    : $layout->commerce_context,
                'allow_global_fallback' => $allowFallback,
                'used_global_fallback' => $layout->commerce_context === CmsCommerceContext::Global
                    && $context !== CmsCommerceContext::Global
                    && $campaign === null,
                'campaign' => $campaign === null ? null : [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'slug' => $campaign->slug,
                    'priority' => (int) $campaign->priority,
                    'promotion_ids' => $campaign->relationLoaded('promotions')
                        ? $campaign->promotions->pluck('id')->values()->all()
                        : [],
                ],
            ],
        ];
    }
}
