import {
  canRedirectLegacyProduct,
  type LegacyEditPolicyProduct,
} from "@/lib/admin/legacy-edit-policy";
import {
  buildCatalogProductEditUrl,
  legacyNumericIdFromCatalogProductId,
} from "@/lib/admin/product-id-map";
import type { AdminCatalogProduct } from "@/lib/api/admin-catalog";
import type { Product } from "@/lib/types/catalog";

/** Normalized input for admin product edit links. */
export type AdminProductEditLinkProduct = LegacyEditPolicyProduct & {
  legacyNumericId: number;
};

export type AdminProductEditUrlDecision = {
  url: string;
  reason: string;
};

/**
 * Required before canonical redirect from link builders:
 * - catalog UUID
 * - explicit legacy-configuration flag
 * - explicit wholesale flags (volume tiers are canonical at both scopes)
 * - catalog product type field present (null = known missing, blocks via policy)
 */
export function isAdminProductEditLinkContextComplete(
  product: LegacyEditPolicyProduct,
): boolean {
  if (!product.id?.trim()) {
    return false;
  }

  if (product.catalogProductTypeId === undefined) {
    return false;
  }

  if (typeof product.legacyConfigurationProduct !== "boolean") {
    return false;
  }

  if (typeof product.hasProductPriceTiers !== "boolean") {
    return false;
  }

  if (typeof product.hasConfigurationPriceTiers !== "boolean") {
    return false;
  }

  return true;
}

export function legacyEditPolicyFromLegacyProduct(product: Product): AdminProductEditLinkProduct {
  return {
    legacyNumericId: product.id,
    id: product.catalogProductId?.trim() || null,
    catalogProductTypeId:
      product.catalogProductTypeId !== undefined ? product.catalogProductTypeId : undefined,
    legacyConfigurationProduct:
      product.legacyConfigurationProduct === undefined
        ? undefined
        : product.legacyConfigurationProduct === true,
    hasProductPriceTiers:
      product.priceTiers === undefined
        ? undefined
        : product.priceTiers.some((tier) => tier.minQuantity >= 1),
    hasConfigurationPriceTiers:
      product.configurations === undefined
        ? undefined
        : product.configurations.some((configuration) =>
            (configuration.priceTiers ?? []).some((tier) => tier.minQuantity >= 1),
          ),
  };
}

export function legacyEditPolicyFromCatalogProduct(
  product: AdminCatalogProduct,
): AdminProductEditLinkProduct {
  return {
    legacyNumericId: legacyNumericIdFromCatalogProductId(product.id),
    id: product.id,
    catalogProductTypeId: product.catalogProductTypeId,
    legacyConfigurationProduct: product.legacyConfigurationProduct,
    hasProductPriceTiers: product.priceTiers.some((tier) => tier.minQuantity >= 1),
    hasConfigurationPriceTiers: product.hasConfigurationPriceTiers,
  };
}

export function normalizeAdminProductEditLinkProduct(
  product: Product | AdminCatalogProduct | AdminProductEditLinkProduct,
): AdminProductEditLinkProduct {
  if ("legacyNumericId" in product && typeof product.legacyNumericId === "number") {
    return product;
  }

  if (typeof (product as Product).id === "number") {
    return legacyEditPolicyFromLegacyProduct(product as Product);
  }

  return legacyEditPolicyFromCatalogProduct(product as AdminCatalogProduct);
}

function legacyEditUrl(legacyNumericId: number): string {
  return `/admin/products/${legacyNumericId}/edit`;
}

/**
 * Resolve admin edit URL and decision reason.
 *
 * Canonical-first: safe products always get `/admin/products?edit={uuid}`.
 * Uses `canRedirectLegacyProduct()` for policy — does not duplicate block rules and
 * does not consult `NEXT_PUBLIC_ADMIN_LEGACY_EDIT_REDIRECT` (numeric route page only).
 */
export function resolveAdminProductEditUrl(
  product: Product | AdminCatalogProduct | AdminProductEditLinkProduct,
): AdminProductEditUrlDecision {
  const linkProduct = normalizeAdminProductEditLinkProduct(product);

  if (!isAdminProductEditLinkContextComplete(linkProduct)) {
    // Fail-safe: wholesale flags or legacy-configuration flag unknown — stay on ProductForm
    // until full admin product context is available (e.g. catalog list row without tier data).
    return {
      url: legacyEditUrl(linkProduct.legacyNumericId),
      reason: "incomplete_product_context",
    };
  }

  const policy = canRedirectLegacyProduct(linkProduct);
  const catalogProductId = linkProduct.id?.trim();

  if (policy.redirect && catalogProductId) {
    return {
      url: buildCatalogProductEditUrl(catalogProductId),
      reason: policy.reason,
    };
  }

  return {
    url: legacyEditUrl(linkProduct.legacyNumericId),
    reason: policy.reason,
  };
}

/**
 * Prefer canonical catalog editor when redirect policy allows; otherwise legacy ProductForm route.
 *
 * Fail-safe: incomplete product context always returns legacy URL.
 */
export function buildAdminProductEditUrl(
  product: Product | AdminCatalogProduct | AdminProductEditLinkProduct,
): string {
  return resolveAdminProductEditUrl(product).url;
}
