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

export type ProductPublishReadinessInput = {
  catalogProductTypeId: string;
  subcategoryId: string;
  catalogProductTypeSubcategoryId?: string | null;
  catalogProductTypeIsActive?: boolean;
  isLeafCategory: boolean;
  price: number;
  commerceChannelCode?: string | null;
  commerceChannelId?: string | null;
  storeId?: string | null;
  hasSimpleInventoryPolicy: boolean;
  variants: Pick<
    AdminProductVariant,
    "isActive" | "price" | "pricesCount" | "inventoriesCount"
  >[];
  isDemo?: boolean;
  /** Required when commerce channel is CHINA_IMPORT. */
  hasPublishableShippingOption?: boolean;
};

export function isLeafCategoryId(
  categoryId: string,
  categories: ReadonlyArray<{ id: string; parentId?: string | null }>,
): boolean {
  if (!categoryId.trim()) {
    return false;
  }

  return !categories.some((category) => category.parentId === categoryId);
}

export function isSellableVariant(
  variant: Pick<AdminProductVariant, "isActive" | "price" | "pricesCount" | "inventoriesCount">,
): boolean {
  if (!variant.isActive) {
    return false;
  }

  const hasPrice = (variant.price ?? 0) > 0 || variant.pricesCount > 0;
  if (!hasPrice) {
    return false;
  }

  return variant.inventoriesCount > 0;
}

function normalizeCommerceChannelCode(code: string | null | undefined): string | null {
  if (!code) {
    return null;
  }

  return code.trim().toUpperCase().replace(/-/g, "_");
}

export function calculateProductPublishReadiness(
  input: ProductPublishReadinessInput,
): ProductPublishReadinessResult {
  const sellableVariants = input.variants.filter(isSellableVariant);
  const variantPath = sellableVariants.length > 0;
  const channelCode = normalizeCommerceChannelCode(input.commerceChannelCode);

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
      id: "china-shipping",
      label: "Shipping option configured",
      met: input.hasPublishableShippingOption === true,
    });
  }

  if (input.isDemo) {
    items.push({
      id: "not-demo",
      label: "Product is not marked as demo",
      met: false,
    });
  }

  if (variantPath) {
    items.push({
      id: "sellable-variant",
      label: "At least one sellable variant with pricing and inventory",
      met: sellableVariants.length > 0,
    });
  } else {
    items.push({
      id: "simple-price",
      label: "Base price greater than zero",
      met: input.price > 0,
    });
    items.push({
      id: "simple-inventory",
      label: "Product-level inventory policy configured",
      met: input.hasSimpleInventoryPolicy,
    });
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
