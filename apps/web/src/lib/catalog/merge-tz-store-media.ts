import type { TzStorefrontStore } from "@/lib/api/tz-stores";

/**
 * Enrich CMS or partial store rows with authoritative Store Engine media fields.
 * Preserves CMS ordering while restoring logo/banner/description from live stores.
 */
export function mergeTzStoreMedia(
  preferred: TzStorefrontStore[],
  authoritative: TzStorefrontStore[],
): TzStorefrontStore[] {
  if (preferred.length === 0) {
    return authoritative;
  }

  const bySlug = new Map(authoritative.map((store) => [store.slug, store]));
  const byId = new Map(authoritative.map((store) => [store.id, store]));

  return preferred.map((store) => {
    const live = bySlug.get(store.slug) ?? byId.get(store.id);
    if (!live) {
      return store;
    }

    return {
      ...store,
      code: store.code || live.code,
      name: store.name || live.name,
      slug: store.slug || live.slug,
      description: store.description ?? live.description,
      theme_color: store.theme_color ?? live.theme_color,
      logo_path: store.logo_path ?? live.logo_path,
      logo_url: store.logo_url ?? live.logo_url,
      banner_path: store.banner_path ?? live.banner_path,
      banner_url: store.banner_url ?? live.banner_url,
      storefront_featured:
        store.storefront_featured ?? live.storefront_featured,
    };
  });
}
