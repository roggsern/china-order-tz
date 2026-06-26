import type { Product, ProductVariantChoice, ProductVariants } from "@/lib/types/catalog";

type LegacyProductVariant = {
  type?: string;
  name?: string;
};

/** System-suggested size options — admin picks a subset per product. */
export const DEFAULT_SIZE_OPTIONS = [
  "XS",
  "S",
  "M",
  "L",
  "XL",
  "XXL",
  "XXXL",
  "XXXXL",
] as const;

export const VARIANT_PRESETS = {
  sizes: DEFAULT_SIZE_OPTIONS,
  colors: ["Red", "Blue", "Black"],
  storage: ["128GB", "256GB", "512GB"],
} as const;

export const EMPTY_VARIANTS: ProductVariants = {};

export const SIZE_REQUIRED_MESSAGE = "Please select a size";

const cleanArray = (arr?: unknown[]): string[] =>
  Array.isArray(arr)
    ? [...new Set(arr.map(String).map((s) => s.trim()).filter(Boolean))]
    : [];

/** Trim, uppercase, dedupe, and drop empty size values. */
export function normalizeProductSizes(sizes?: unknown[] | null): string[] {
  if (!Array.isArray(sizes)) return [];

  const seen = new Set<string>();
  const result: string[] = [];

  for (const entry of sizes) {
    const normalized = String(entry).trim().toUpperCase();
    if (!normalized || seen.has(normalized)) continue;
    seen.add(normalized);
    result.push(normalized);
  }

  return result.sort(
    (a, b) =>
      DEFAULT_SIZE_OPTIONS.indexOf(a as (typeof DEFAULT_SIZE_OPTIONS)[number]) -
        DEFAULT_SIZE_OPTIONS.indexOf(b as (typeof DEFAULT_SIZE_OPTIONS)[number]) ||
      a.localeCompare(b),
  );
}

export function normalizeSelectedSize(size?: string | null): string | null {
  const normalized = size?.trim().toUpperCase();
  return normalized || null;
}

function hasAnyVariants(variants: ProductVariants): boolean {
  return Boolean(
    variants.sizes?.length || variants.colors?.length || variants.storage?.length,
  );
}

/**
 * Sanitize raw variant input once. Never calls normalizeProductVariants or hasVariants.
 */
export function cleanProductVariants(variants: unknown): ProductVariants {
  if (!variants || typeof variants !== "object") {
    return EMPTY_VARIANTS;
  }

  if (Array.isArray(variants)) {
    const migrated: ProductVariants = {};

    for (const entry of variants) {
      if (!entry || typeof entry !== "object") continue;

      const legacy = entry as LegacyProductVariant;
      const name = String(legacy.name ?? "").trim();
      if (!name) continue;

      if (legacy.type === "size") {
        migrated.sizes = [...(migrated.sizes ?? []), name];
      } else if (legacy.type === "color") {
        migrated.colors = [...(migrated.colors ?? []), name];
      } else if (legacy.type === "storage") {
        migrated.storage = [...(migrated.storage ?? []), name];
      }
    }

    const cleaned: ProductVariants = {};
    const sizes = normalizeProductSizes(migrated.sizes);
    const colors = cleanArray(migrated.colors);
    const storage = cleanArray(migrated.storage);

    if (sizes.length) cleaned.sizes = sizes;
    if (colors.length) cleaned.colors = colors;
    if (storage.length) cleaned.storage = storage;

    return cleaned;
  }

  const record = variants as ProductVariants;
  const cleaned: ProductVariants = {};
  const sizes = normalizeProductSizes(record.sizes);
  const colors = cleanArray(record.colors);
  const storage = cleanArray(record.storage);

  if (sizes.length) cleaned.sizes = sizes;
  if (colors.length) cleaned.colors = colors;
  if (storage.length) cleaned.storage = storage;

  return cleaned;
}

export function normalizeProductVariants(
  variants?: ProductVariants | LegacyProductVariant[] | null,
): ProductVariants | undefined {
  const cleaned = cleanProductVariants(variants);
  return hasAnyVariants(cleaned) ? cleaned : undefined;
}

export function hasVariants(product: Pick<Product, "variants">): boolean {
  return hasAnyVariants(cleanProductVariants(product.variants));
}

export function getProductVariants(product: Pick<Product, "variants">): ProductVariants {
  return normalizeProductVariants(product.variants) ?? EMPTY_VARIANTS;
}

export function getProductSizes(product: Pick<Product, "variants">): string[] {
  return getProductVariants(product).sizes ?? [];
}

export function hasSizeVariants(product: Pick<Product, "variants">): boolean {
  return getProductSizes(product).length > 0;
}

export function hasSelectableVariants(product: Pick<Product, "variants">): boolean {
  const options = getProductVariants(product);
  return (
    (options.sizes?.length ?? 0) > 0 ||
    (options.colors?.length ?? 0) > 0 ||
    (options.storage?.length ?? 0) > 0
  );
}

export function isValidSizeForProduct(
  product: Pick<Product, "variants">,
  size?: string | null,
): boolean {
  const sizes = getProductSizes(product);
  if (sizes.length === 0) return true;

  const normalized = normalizeSelectedSize(size);
  if (!normalized) return false;

  return sizes.includes(normalized);
}

export function isSizeSelectionRequired(product: Pick<Product, "variants">): boolean {
  return hasSizeVariants(product);
}

export function canAddProductToCart(
  product: Pick<Product, "variants">,
  variant?: ProductVariantChoice | null,
): boolean {
  if (!isSizeSelectionRequired(product)) return true;
  return isValidSizeForProduct(product, variant?.size);
}

export function toggleSizeSelection(selected: string[], size: string): string[] {
  return normalizeProductSizes(
    selected.includes(size)
      ? selected.filter((entry) => entry !== size)
      : [...selected, size],
  );
}

export function parseCommaList(value: string): string[] {
  return cleanArray(value.split(","));
}

export function formatCommaList(values?: string[]): string {
  return (values ?? []).join(", ");
}

export function formatVariantLabel(variant: ProductVariantChoice = {}): string[] {
  const labels: string[] = [];

  if (variant.size) labels.push(`Size: ${variant.size}`);
  if (variant.color) labels.push(`Color: ${variant.color}`);
  if (variant.storage) labels.push(`Storage: ${variant.storage}`);

  return labels;
}

export function variantKey(variant: ProductVariantChoice = {}): string {
  const parts = [
    variant.size ? `size:${variant.size}` : "",
    variant.color ? `color:${variant.color}` : "",
    variant.storage ? `storage:${variant.storage}` : "",
  ].filter(Boolean);

  return parts.length > 0 ? parts.join("|") : "default";
}

export function createCartItemId(productId: number, variant: ProductVariantChoice = {}): string {
  const key = variantKey(variant);
  return key === "default" ? `cart-item-${productId}` : `cart-item-${productId}-${key}`;
}

type VariantChoiceInput =
  | ProductVariantChoice
  | { size?: string; color?: string; storage?: string; selectedSize?: string }
  | null
  | undefined;

export function normalizeVariantChoice(value?: VariantChoiceInput): ProductVariantChoice | undefined {
  if (!value) return undefined;

  const legacySelectedSize =
    "selectedSize" in value ? value.selectedSize?.trim() : undefined;

  const variant: ProductVariantChoice = {
    size: normalizeSelectedSize(value.size) ?? normalizeSelectedSize(legacySelectedSize) ?? undefined,
    color: value.color?.trim() || undefined,
    storage: value.storage?.trim() || undefined,
  };

  return variant.size || variant.color || variant.storage ? variant : undefined;
}

export function getSelectedSize(variant?: ProductVariantChoice | null): string | null {
  return normalizeSelectedSize(variant?.size);
}
