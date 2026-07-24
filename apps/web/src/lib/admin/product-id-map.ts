/**
 * Legacy admin product routes (`/admin/products/[numericId]/edit`) use a deterministic
 * numeric hash of the Laravel catalog product UUID. The canonical catalog editor uses
 * UUIDs directly (`/admin/products?edit={uuid}`).
 *
 * Phase B redirect (not enabled yet):
 * ```ts
 * const catalogId = resolveCatalogProductIdFromLegacyNumericId(numericId, products);
 * if (catalogId) router.replace(buildCatalogProductEditUrl(catalogId));
 * ```
 */

export type LegacyProductIdCandidate = {
  id: number;
  catalogProductId?: string | null;
};

export type CatalogProductEditTab =
  | "details"
  | "media"
  | "specifications"
  | "variants"
  | "shipping"
  | "stock";

const EDIT_TABS = new Set<CatalogProductEditTab>([
  "details",
  "media",
  "specifications",
  "variants",
  "shipping",
  "stock",
]);

/** Same hash used by `mapAdminApiProductToProduct` for legacy `Product.id`. */
export function legacyNumericIdFromCatalogProductId(catalogProductId: string): number {
  let hash = 0;

  for (let index = 0; index < catalogProductId.length; index += 1) {
    hash = (hash * 31 + catalogProductId.charCodeAt(index)) >>> 0;
  }

  return hash || 1;
}

export function catalogProductIdMatchesLegacyNumericId(
  catalogProductId: string,
  legacyNumericId: number,
): boolean {
  return legacyNumericIdFromCatalogProductId(catalogProductId) === legacyNumericId;
}

/**
 * Resolve a legacy numeric route id to the catalog product UUID by scanning loaded
 * admin products (from `AdminProductsProvider` / `fetchAdminProducts`).
 */
export function resolveCatalogProductIdFromLegacyNumericId(
  legacyNumericId: number,
  products: ReadonlyArray<LegacyProductIdCandidate>,
): string | null {
  if (!Number.isFinite(legacyNumericId) || legacyNumericId <= 0) {
    return null;
  }

  const match = products.find(
    (product) =>
      product.id === legacyNumericId &&
      typeof product.catalogProductId === "string" &&
      product.catalogProductId.trim() !== "",
  );

  return match?.catalogProductId?.trim() ?? null;
}

/** Canonical catalog edit deep link (Phase A). */
export function buildCatalogProductEditUrl(
  catalogProductId: string,
  tab?: CatalogProductEditTab,
): string {
  const params = new URLSearchParams();
  params.set("edit", catalogProductId.trim());

  if (tab && tab !== "details" && EDIT_TABS.has(tab)) {
    params.set("tab", tab);
  }

  return `/admin/products?${params.toString()}`;
}

export function parseCatalogProductEditTab(
  value: string | null | undefined,
): CatalogProductEditTab {
  if (value && EDIT_TABS.has(value as CatalogProductEditTab)) {
    return value as CatalogProductEditTab;
  }

  return "details";
}
