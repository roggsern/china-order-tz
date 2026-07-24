import {
  catalogProductIdMatchesLegacyNumericId,
  resolveCatalogProductIdFromLegacyNumericId,
  type LegacyProductIdCandidate,
} from "@/lib/admin/product-id-map";
import { fetchAdminCatalogProductsPage } from "@/lib/api/admin-catalog";

/**
 * RC1-A5.1 / RC1-C01 — Canonical-first redirect from `/admin/products/[numericId]/edit`
 * to `/admin/products?edit={uuid}` when `canRedirectLegacyProduct()` allows it.
 *
 * Default ON. Set `NEXT_PUBLIC_ADMIN_LEGACY_EDIT_REDIRECT=0|false|no` to opt out and keep
 * ProductForm as the default for the numeric edit route (rollback / local debugging only).
 *
 * Link builders (`buildAdminProductEditUrl`) always use redirect policy and never consult
 * this flag.
 */
export function isLegacyEditRedirectEnabled(
  flagValue: string | null | undefined = process.env.NEXT_PUBLIC_ADMIN_LEGACY_EDIT_REDIRECT,
): boolean {
  const normalized = flagValue?.trim().toLowerCase();

  if (normalized === "0" || normalized === "false" || normalized === "no") {
    return false;
  }

  return true;
}

/**
 * Resolve legacy numeric route id → catalog UUID.
 * 1) Scan in-memory candidates (AdminProductsProvider)
 * 2) Paginated admin products API fallback (does not rely on browser cache alone)
 *
 * Redirect eligibility rules live in `legacy-edit-policy.ts` (`canRedirectLegacyProduct`).
 */
export async function resolveCatalogProductIdForLegacyEdit(
  legacyNumericId: number,
  cachedProducts: ReadonlyArray<LegacyProductIdCandidate> = [],
): Promise<string | null> {
  if (!Number.isFinite(legacyNumericId) || legacyNumericId <= 0) {
    return null;
  }

  const fromCache = resolveCatalogProductIdFromLegacyNumericId(
    legacyNumericId,
    cachedProducts,
  );
  if (fromCache) {
    return fromCache;
  }

  const directCatalogId = cachedProducts.find(
    (product) =>
      typeof product.catalogProductId === "string" &&
      product.catalogProductId.trim() !== "" &&
      catalogProductIdMatchesLegacyNumericId(product.catalogProductId, legacyNumericId),
  )?.catalogProductId;

  if (directCatalogId?.trim()) {
    return directCatalogId.trim();
  }

  let page = 1;
  let lastPage = 1;

  do {
    const result = await fetchAdminCatalogProductsPage({ page, perPage: 100 });
    lastPage = result.lastPage;

    const match = result.items.find((item) =>
      catalogProductIdMatchesLegacyNumericId(item.id, legacyNumericId),
    );

    if (match) {
      return match.id;
    }

    page += 1;
  } while (page <= lastPage);

  return null;
}
