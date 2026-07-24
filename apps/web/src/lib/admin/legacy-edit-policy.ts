import { resolveCatalogProductIdForLegacyEdit } from "@/lib/admin/legacy-edit-redirect";
import type { LegacyProductIdCandidate } from "@/lib/admin/product-id-map";
import type { AdminApiProduct } from "@/lib/api/admin-catalog";

/**
 * Input for legacy → canonical edit redirect policy.
 * Populate from admin product API (`GET /api/admin/products/{id}`).
 */
export type LegacyEditPolicyProduct = {
  /** Catalog product UUID. Missing => stay legacy (`uuid_unresolved`). */
  id?: string | null;
  catalogProductTypeId?: string | null;
  legacyConfigurationProduct?: boolean;
  /** Product-level MOQ / wholesale tiers (`price_tiers` on ProductResource). */
  hasProductPriceTiers?: boolean;
  /** Per-configuration tiers on variants/configurations. */
  hasConfigurationPriceTiers?: boolean;
  /** Soft blockers — documented only; do not block redirect. */
  weight?: number | null;
  compareAtPrice?: number | null;
  isDemo?: boolean;
  lifecycleStatus?: string | null;
  hasRichDescription?: boolean;
};

export type LegacyEditRedirectPolicyResult = {
  redirect: boolean;
  reason: string;
};

/** Documented soft gaps where ProductForm still has richer editing (non-blocking). */
export const LEGACY_EDIT_SOFT_BLOCKERS = [
  "weight",
  "compare_at_price",
  "is_demo",
  "out_of_stock_lifecycle",
  "rich_text_description",
] as const;

function tierIsActive(tier: { min_quantity?: number | string | null }): boolean {
  const minQuantity = Number(tier.min_quantity ?? 0);

  return Number.isFinite(minQuantity) && minQuantity >= 1;
}

/**
 * Detect wholesale / MOQ dependency from admin product API payload.
 */
export function detectWholesalePricingDependency(product: AdminApiProduct): {
  hasProductPriceTiers: boolean;
  hasConfigurationPriceTiers: boolean;
} {
  const productTiers = product.price_tiers ?? [];
  const variantRows = product.configurations ?? product.variants ?? [];

  const hasProductPriceTiers = productTiers.some(
    (tier) => tierIsActive(tier) && !tier.configuration_id,
  );

  const hasConfigurationPriceTiers =
    productTiers.some((tier) => tierIsActive(tier) && Boolean(tier.configuration_id)) ||
    variantRows.some((row) => (row.price_tiers ?? []).some(tierIsActive));

  return { hasProductPriceTiers, hasConfigurationPriceTiers };
}

export function mapAdminApiProductToLegacyEditPolicyProduct(
  product: AdminApiProduct,
): LegacyEditPolicyProduct {
  const wholesale = detectWholesalePricingDependency(product);
  const description = product.description?.trim() ?? "";
  const shortDescription = product.short_description?.trim() ?? "";
  const compareRaw = product.compare_at_price;
  const compareParsed =
    compareRaw === null || compareRaw === undefined || compareRaw === ""
      ? null
      : Number.parseFloat(String(compareRaw));
  const weightRaw = product.weight;
  const weightParsed =
    weightRaw === null || weightRaw === undefined || weightRaw === ""
      ? null
      : Number.parseFloat(String(weightRaw));

  return {
    id: product.id,
    catalogProductTypeId:
      product.catalog_product_type_id ?? product.catalog_product_type?.id ?? null,
    legacyConfigurationProduct: product.legacy_configuration_product === true,
    hasProductPriceTiers: wholesale.hasProductPriceTiers,
    hasConfigurationPriceTiers: wholesale.hasConfigurationPriceTiers,
    weight: Number.isFinite(weightParsed) ? weightParsed : null,
    compareAtPrice: Number.isFinite(compareParsed) ? compareParsed : null,
    isDemo: product.is_demo === true,
    lifecycleStatus: product.lifecycle_status ?? product.status ?? null,
    hasRichDescription: description.length > 0 && description !== shortDescription,
  };
}

export function listLegacyEditSoftBlockers(
  product: LegacyEditPolicyProduct,
): string[] {
  const blockers: string[] = [];

  if (product.weight != null && product.weight > 0) {
    blockers.push("weight");
  }
  if (product.compareAtPrice != null && product.compareAtPrice > 0) {
    blockers.push("compare_at_price");
  }
  if (product.isDemo) {
    blockers.push("is_demo");
  }
  if (product.lifecycleStatus === "out_of_stock") {
    blockers.push("out_of_stock_lifecycle");
  }
  if (product.hasRichDescription) {
    blockers.push("rich_text_description");
  }

  return blockers;
}

/**
 * Central redirect eligibility for legacy `/admin/products/[numericId]/edit`.
 *
 * Hard blockers (stay on ProductForm — these products require legacy workflows):
 *
 * | reason                         | Why legacy is required                                      |
 * |--------------------------------|-------------------------------------------------------------|
 * | uuid_unresolved                | Cannot open canonical panel without catalog UUID            |
 * | legacy_configuration_product | Configuration Template grid only exists in ProductForm      |
 * | missing_catalog_product_type   | Canonical editor requires catalog product type assignment   |
 * | wholesale_pricing              | MOQ / wholesale tiers only editable in ProductForm            |
 *
 * Soft blockers (weight, compare-at, demo, out_of_stock, rich text) are documented
 * via `listLegacyEditSoftBlockers()` but do not block redirect.
 */
export function canRedirectLegacyProduct(
  product: LegacyEditPolicyProduct,
): LegacyEditRedirectPolicyResult {
  const catalogProductId = product.id?.trim() || null;

  if (!catalogProductId) {
    // Legacy numeric id could not be mapped to a catalog UUID.
    return { redirect: false, reason: "uuid_unresolved" };
  }

  if (product.legacyConfigurationProduct) {
    // SyncProductConfigurations / attribute combo grid is legacy-only.
    return { redirect: false, reason: "legacy_configuration_product" };
  }

  if (!product.catalogProductTypeId?.trim()) {
    // Canonical Details tab, publish readiness, and taxonomy require CPT.
    return { redirect: false, reason: "missing_catalog_product_type" };
  }

  if (product.hasProductPriceTiers || product.hasConfigurationPriceTiers) {
    // WholesalePricingEditor and per-configuration tiers are legacy-only.
    return { redirect: false, reason: "wholesale_pricing" };
  }

  return { redirect: true, reason: "safe_for_canonical" };
}

export async function fetchLegacyEditPolicyProduct(
  catalogProductId: string,
): Promise<LegacyEditPolicyProduct> {
  const trimmed = catalogProductId.trim();

  if (!trimmed) {
    return { id: null };
  }

  const response = await fetch(`/api/admin/products/${encodeURIComponent(trimmed)}`, {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const payload = (await response.json()) as {
    success?: boolean;
    data?: AdminApiProduct;
  };

  if (!response.ok || payload.success === false || !payload.data?.id) {
    return { id: trimmed };
  }

  return mapAdminApiProductToLegacyEditPolicyProduct(payload.data);
}

/** Resolve numeric legacy id, fetch admin product, map to policy input. */
export async function loadLegacyEditPolicyProduct(
  legacyNumericId: number,
  cachedProducts: ReadonlyArray<LegacyProductIdCandidate> = [],
): Promise<LegacyEditPolicyProduct> {
  const catalogProductId = await resolveCatalogProductIdForLegacyEdit(
    legacyNumericId,
    cachedProducts,
  );

  if (!catalogProductId) {
    return { id: null };
  }

  try {
    return await fetchLegacyEditPolicyProduct(catalogProductId);
  } catch {
    return { id: catalogProductId };
  }
}
