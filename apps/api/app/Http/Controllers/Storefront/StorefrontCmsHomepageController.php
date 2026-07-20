<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\CMS\ResolveStorefrontHomepageAction;
use App\Enums\CMS\CmsCommerceContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\CmsHomepageLayoutResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StorefrontCmsHomepageController extends Controller
{
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

        $resolved = $action->handle($context, $allowFallback);
        $layout = $resolved['layout'];
        $campaign = $resolved['campaign'];

        if ($layout === null) {
            return response()->json([
                'success' => true,
                'data' => null,
                'meta' => [
                    'commerce_context' => $context->value,
                    'allow_global_fallback' => $allowFallback,
                    'campaign' => null,
                    'message' => 'No active campaign or default homepage layout for this context.',
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => new CmsHomepageLayoutResource($layout),
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
        ]);
    }
}
