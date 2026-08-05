export type ProductConditionValue =
  | "BRAND_NEW"
  | "OPEN_BOX"
  | "REFURBISHED"
  | "USED";

export const PRODUCT_CONDITION_OPTIONS: Array<{
  value: ProductConditionValue;
  label: string;
}> = [
  { value: "BRAND_NEW", label: "Brand New" },
  { value: "OPEN_BOX", label: "Open Box" },
  { value: "REFURBISHED", label: "Refurbished" },
  { value: "USED", label: "Used / Second Hand" },
];

export function productConditionLabel(
  value: ProductConditionValue | string | null | undefined,
): string | null {
  if (!value) {
    return null;
  }

  return PRODUCT_CONDITION_OPTIONS.find((option) => option.value === value)?.label
    ?? null;
}

export function isProductConditionValue(
  value: string | null | undefined,
): value is ProductConditionValue {
  return PRODUCT_CONDITION_OPTIONS.some((option) => option.value === value);
}
