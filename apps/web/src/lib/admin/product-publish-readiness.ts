import type { AdminProductVariant } from "@/lib/api/admin-catalog";

export type PublishReadinessItem = {
  id: string;
  label: string;
  met: boolean;
};

export type ProductPublishReadinessResult = {
  ready: boolean;
  items: PublishReadinessItem[];
  missing: PublishReadinessItem[];
  completed: PublishReadinessItem[];
  path: "simple" | "variant";
};

export type PublishReadinessVariant = Pick<
  AdminProductVariant,
  | "isActive"
  | "price"
  | "pricesCount"
  | "inventoriesCount"
  | "hasActiveCommercialStock"
  | "commercialStocksCount"
>;

export type ProductPublishReadinessInput = {
  catalogProductTypeId: string;
  subcategoryId: string;
  catalogProductTypeSubcategoryId?: string | null;
  catalogProductTypeIsActive?: boolean;
  isLeafCategory: boolean;
  price: number;
  /** When "variants", never fall through to the simple base-price blocker. */
  pricingModel?: "simple" | "variants" | null;
  commerceChannelCode?: string | null;
  commerceChannelId?: string | null;
  storeId?: string | null;
  hasSimpleInventoryPolicy: boolean;
  variants: PublishReadinessVariant[];
  isDemo?: boolean;
  /** Required when commerce channel is CHINA_IMPORT. */
  hasPublishableShippingOption?: boolean;
  /** Required when commerce channel is CHINA_IMPORT. */
  supplierId?: string | null;
  /** Product-level catalog images (product_media). */
  hasCatalogImage?: boolean;
  /** Explicit primary catalog image flag. */
  hasPrimaryImage?: boolean;
  /**
   * When false, required product-type specifications are incomplete.
   * Defaults to true when omitted (no required attributes / not loaded yet for unit tests).
   */
  requiredSpecificationsComplete?: boolean;
  missingRequiredSpecificationLabels?: string[];
};

export type PublishReadinessSpecAttribute = {
  isRequired: boolean;
  name: string;
  value: { display: string | null };
};

/** Aligns with backend GetProductCatalogAttributesAction display + is_required. */
export function evaluateRequiredSpecifications(
  attributes: ReadonlyArray<PublishReadinessSpecAttribute>,
): { complete: boolean; missingLabels: string[] } {
  const missingLabels = attributes
    .filter((attribute) => attribute.isRequired)
    .filter((attribute) => {
      const display = attribute.value.display;
      return display === null || display.trim() === "";
    })
    .map((attribute) => attribute.name);

  return {
    complete: missingLabels.length === 0,
    missingLabels,
  };
}

export function isLeafCategoryId(
  categoryId: string,
  categories: ReadonlyArray<{ id: string; parentId?: string | null }>,
): boolean {
  if (!categoryId.trim()) {
    return false;
  }

  return !categories.some((category) => category.parentId === categoryId);
}

export function normalizeCommerceChannelCode(code: string | null | undefined): string | null {
  if (!code) {
    return null;
  }

  return code.trim().toUpperCase().replace(/-/g, "_");
}

export function variantHasRetailPrice(variant: PublishReadinessVariant): boolean {
  return (variant.price ?? 0) > 0 || variant.pricesCount > 0;
}

/** Stock policy aligned with backend StockResolver (warehouse vs commercial). */
export function variantHasStockPolicy(
  variant: PublishReadinessVariant,
  commerceChannelCode?: string | null,
): boolean {
  const channelCode = normalizeCommerceChannelCode(commerceChannelCode);

  if (channelCode === "CHINA_IMPORT") {
    return (
      variant.hasActiveCommercialStock === true || (variant.commercialStocksCount ?? 0) > 0
    );
  }

  return variant.inventoriesCount > 0;
}

export function isSellableVariant(
  variant: PublishReadinessVariant,
  commerceChannelCode?: string | null,
): boolean {
  if (!variant.isActive) {
    return false;
  }

  if (!variantHasRetailPrice(variant)) {
    return false;
  }

  return variantHasStockPolicy(variant, commerceChannelCode);
}

function usesVariantPublishPath(input: ProductPublishReadinessInput): boolean {
  if (input.pricingModel === "variants") {
    return true;
  }

  return input.variants.length > 0;
}

export function calculateProductPublishReadiness(
  input: ProductPublishReadinessInput,
): ProductPublishReadinessResult {
  const channelCode = normalizeCommerceChannelCode(input.commerceChannelCode);
  const variantPath = usesVariantPublishPath(input);
  const sellableVariants = input.variants.filter((variant) =>
    isSellableVariant(variant, channelCode),
  );

  const items: PublishReadinessItem[] = [
    {
      id: "catalog-product-type",
      label: "Catalog product type selected",
      met: input.catalogProductTypeId.trim().length > 0,
    },
    {
      id: "catalog-product-type-active",
      label: "Catalog product type is active",
      met: input.catalogProductTypeIsActive !== false,
    },
    {
      id: "leaf-category",
      label: "Leaf category assigned",
      met: input.subcategoryId.trim().length > 0 && input.isLeafCategory,
    },
    {
      id: "catalog-product-type-category-match",
      label: "Product type matches leaf category",
      met:
        !input.catalogProductTypeSubcategoryId ||
        input.catalogProductTypeSubcategoryId === input.subcategoryId,
    },
    {
      id: "commerce-channel",
      label: "Commerce channel assigned",
      met: Boolean(channelCode || input.commerceChannelId),
    },
  ];

  if (channelCode === "TZ_LOCAL") {
    items.push({
      id: "tz-store",
      label: "Store assigned",
      met: Boolean(input.storeId?.trim()),
    });
  }

  if (channelCode === "CHINA_IMPORT") {
    items.push({
      id: "china-supplier",
      label: "Supplier assigned",
      met: Boolean(input.supplierId?.trim()),
    });
    items.push({
      id: "china-shipping",
      label: "Shipping option configured",
      met: input.hasPublishableShippingOption === true,
    });
  }

  const hasCatalogImage = input.hasCatalogImage === true;
  const hasPrimaryImage = input.hasPrimaryImage === true;

  items.push({
    id: "catalog-image",
    label: "Catalog image uploaded",
    met: hasCatalogImage,
  });
  items.push({
    id: "primary-image",
    label: "Primary image set",
    met: hasCatalogImage && hasPrimaryImage,
  });

  const requiredSpecsComplete = input.requiredSpecificationsComplete !== false;
  const missingSpecLabels = (input.missingRequiredSpecificationLabels ?? []).filter(
    (label) => label.trim().length > 0,
  );
  items.push({
    id: "required-specifications",
    label:
      !requiredSpecsComplete && missingSpecLabels.length > 0
        ? `Required specifications incomplete: ${missingSpecLabels.join(", ")}`
        : "Required specifications complete",
    met: requiredSpecsComplete,
  });

  if (input.isDemo) {
    items.push({
      id: "not-demo",
      label: "Product is not marked as demo",
      met: false,
    });
  }

  if (variantPath) {
    const activeVariants = input.variants.filter((variant) => variant.isActive);
    const hasRetailPricing = activeVariants.some((variant) => variantHasRetailPrice(variant));
    const hasStockPolicy = activeVariants.some((variant) =>
      variantHasStockPolicy(variant, channelCode),
    );

    items.push({
      id: "variant-retail-pricing",
      label: "Variant retail pricing complete",
      met: hasRetailPricing,
    });

    items.push({
      id:
        channelCode === "CHINA_IMPORT"
          ? "variant-commercial-stock"
          : "variant-warehouse-inventory",
      label:
        channelCode === "CHINA_IMPORT"
          ? "Variant commercial stock configured"
          : "Variant warehouse inventory configured",
      met: hasStockPolicy,
    });

    items.push({
      id: "sellable-variant",
      label:
        channelCode === "CHINA_IMPORT"
          ? "At least one sellable variant with retail pricing and commercial stock"
          : "At least one sellable variant with pricing and inventory",
      met: sellableVariants.length > 0,
    });
  } else {
    items.push({
      id: "simple-price",
      label: "Base price greater than zero",
      met: input.price > 0,
    });

    if (channelCode !== "CHINA_IMPORT") {
      items.push({
        id: "simple-inventory",
        label: "Product-level inventory policy configured",
        met: input.hasSimpleInventoryPolicy,
      });
    }
  }

  const missing = items.filter((item) => !item.met);
  const completed = items.filter((item) => item.met);

  return {
    ready: missing.length === 0,
    items,
    missing,
    completed,
    path: variantPath ? "variant" : "simple",
  };
}

export function formatPublishReadinessMissingLabels(result: ProductPublishReadinessResult): string {
  return result.missing.map((item) => item.label).join(", ");
}
