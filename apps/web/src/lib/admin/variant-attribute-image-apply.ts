import type {
  AdminProductVariant,
  AdminVariantAttribute,
} from "@/lib/api/admin-catalog";

/** Prefer Color when present; otherwise first select-like attribute with options. */
export function pickDefaultAttributeForImageApply(
  attributes: readonly AdminVariantAttribute[],
): AdminVariantAttribute | null {
  if (attributes.length === 0) {
    return null;
  }

  const color = attributes.find(
    (attr) => attr.slug === "color" || attr.name.trim().toLowerCase() === "color",
  );
  if (color && color.options.length > 0) {
    return color;
  }

  return attributes.find((attr) => attr.options.length > 0) ?? null;
}

export function countVariantsForAttributeOption(
  variants: readonly AdminProductVariant[],
  optionId: string,
): number {
  if (!optionId) {
    return 0;
  }

  return variants.filter((variant) =>
    variant.attributeValues.some((row) => row.optionId === optionId),
  ).length;
}

export function formatAttributeOptionApplySummary(input: {
  optionValue: string;
  attributeName: string | null | undefined;
  matchedCount: number;
  appliedCount: number;
  skippedCount: number;
}): string {
  const label = input.attributeName
    ? `${input.attributeName}: ${input.optionValue}`
    : input.optionValue;

  if (input.appliedCount === 0 && input.skippedCount > 0) {
    return `No new images applied for ${label}. ${input.skippedCount} variant${input.skippedCount === 1 ? "" : "s"} already had images.`;
  }

  const applied = `Applied image to ${input.appliedCount} of ${input.matchedCount} ${label} variant${input.matchedCount === 1 ? "" : "s"}`;
  if (input.skippedCount > 0) {
    return `${applied} (${input.skippedCount} skipped — already had images).`;
  }

  return `${applied}.`;
}

export function buildAttributeOptionApplyFormFields(input: {
  catalogAttributeOptionId: string;
  altText?: string;
  title?: string;
}): Record<string, string> {
  const fields: Record<string, string> = {
    catalog_attribute_option_id: input.catalogAttributeOptionId,
  };
  if (input.altText) {
    fields.alt_text = input.altText;
  }
  if (input.title) {
    fields.title = input.title;
  }
  return fields;
}
