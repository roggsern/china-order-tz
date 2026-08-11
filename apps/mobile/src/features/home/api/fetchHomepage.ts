import { apiClient } from '@/src/core/api';
import { mapHomepageResponse, resolveHomepageCommerceContext } from '../map/mapHomepage';
import type { HomepageCommerceContext, HomepageViewModel } from '../models/types';
import type { CommerceJourney } from '@/src/shared/types/commerce';

export type FetchHomepageOptions = {
  commerceContext: HomepageCommerceContext;
  allowGlobalFallback?: boolean;
};

/**
 * GET /storefront/homepage — public CMS layout (Contract v1).
 */
export async function fetchHomepage(
  options: FetchHomepageOptions,
): Promise<HomepageViewModel> {
  const response = await apiClient.get<unknown>(
    '/storefront/homepage',
    {
      commerce_context: options.commerceContext,
      allow_global_fallback:
        options.allowGlobalFallback === false ? 0 : 1,
    },
    null,
  );

  return mapHomepageResponse(response);
}

export async function fetchHomepageForJourney(
  journey: CommerceJourney,
  allowGlobalFallback = true,
): Promise<HomepageViewModel> {
  return fetchHomepage({
    commerceContext: resolveHomepageCommerceContext(journey),
    allowGlobalFallback,
  });
}
