<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\CMS\ResolveStorefrontNavigationAction;
use App\Enums\CMS\CmsCommerceContext;
use App\Enums\CMS\CmsNavigationType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StorefrontCmsNavigationController extends Controller
{
    public function show(Request $request, ResolveStorefrontNavigationAction $action): JsonResponse
    {
        $validated = $request->validate([
            'commerce_context' => ['required', Rule::enum(CmsCommerceContext::class)],
            'navigation_type' => ['sometimes', 'nullable', Rule::enum(CmsNavigationType::class)],
            'audience' => ['sometimes', Rule::in(['guest', 'authenticated', 'admin_preview'])],
            'hydrate_mega_menus' => ['sometimes', 'boolean'],
        ]);

        $context = CmsCommerceContext::from($validated['commerce_context']);
        $type = isset($validated['navigation_type']) && $validated['navigation_type'] !== null
            ? CmsNavigationType::from($validated['navigation_type'])
            : null;
        $audience = $validated['audience'] ?? 'guest';
        $hydrate = array_key_exists('hydrate_mega_menus', $validated)
            ? (bool) $validated['hydrate_mega_menus']
            : true;

        return response()->json([
            'success' => true,
            'data' => $action->handle($context, $type, $audience, $hydrate),
        ]);
    }
}
