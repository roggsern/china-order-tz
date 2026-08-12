import type { CommerceJourney } from '@/src/shared/types/commerce';
import { buildProductHref } from '../map/journeyRoutes';

export const TZ_STORE_REQUIRED_MESSAGE =
  'This TZ product is missing store information. Open Shop, choose a store, and pick the product from that store.';

export const TZ_JOURNEY_AMBIGUOUS_MESSAGE =
  'This product could not be opened because its marketplace is unclear.';

function normalizeSlug(value: string | null | undefined): string | null {
  if (typeof value !== 'string') return null;
  const trimmed = value.trim();
  return trimmed === '' ? null : trimmed;
}

/**
 * Product-level TZ store only — never substitutes selected/first store.
 */
export function resolveOwnedTzStoreSlug(params: {
  productStoreSlug?: string | null;
}): string | null {
  return normalizeSlug(params.productStoreSlug);
}

export type SafeProductHrefResult =
  | { ok: true; href: string; storeSlug: string | null }
  | { ok: false; message: string };

/**
 * Build a navigable product href.
 *
 * TZ Home/Search/deep-link: require product-owned storeSlug (fail closed).
 * TZ Browse: may pass browseScopedStoreSlug because products were fetched in that store.
 * Never use first/selected unrelated store for Home/Search.
 */
export function buildSafeProductHref(params: {
  slug: string;
  journey: CommerceJourney;
  productStoreSlug?: string | null;
  /**
   * Only when the product list was already fetched under this store (Browse).
   * Must not be used for CMS/Search/deep-link ownership.
   */
  browseScopedStoreSlug?: string | null;
}): SafeProductHrefResult {
  if (params.journey !== 'TZ_LOCAL') {
    return {
      ok: true,
      storeSlug: null,
      href: buildProductHref({
        slug: params.slug,
        journey: params.journey,
        storeSlug: null,
      }),
    };
  }

  const storeSlug =
    resolveOwnedTzStoreSlug({ productStoreSlug: params.productStoreSlug }) ??
    normalizeSlug(params.browseScopedStoreSlug);

  if (!storeSlug) {
    return { ok: false, message: TZ_STORE_REQUIRED_MESSAGE };
  }

  return {
    ok: true,
    storeSlug,
    href: buildProductHref({
      slug: params.slug,
      journey: 'TZ_LOCAL',
      storeSlug,
    }),
  };
}

/** Which catalog surface Browse should show for the active journey. */
export function browseCatalogKind(
  journey: CommerceJourney,
): 'china' | 'tz' {
  return journey === 'TZ_LOCAL' ? 'tz' : 'china';
}
