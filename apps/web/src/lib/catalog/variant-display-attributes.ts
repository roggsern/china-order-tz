export type VariantDisplayAttribute = {
  attribute: string;
  value: string;
};

export type VariantAttributeValueLike = {
  attribute?: { name?: string | null; slug?: string | null } | null;
  attribute_name?: string | null;
  value?: string | null;
  option?: { label?: string | null; value?: string | null } | null;
  option_value?: string | null;
  display?: string | null;
};

/**
 * Prefer normalized display_attributes (catalog-first API contract).
 * Fall back to legacy/nested attribute_values for older payloads.
 */
export function mapVariantDisplayAttributes(input: {
  display_attributes?: Array<{ attribute?: string | null; value?: string | null }> | null;
  attribute_values?: VariantAttributeValueLike[] | null;
}): VariantDisplayAttribute[] {
  const normalized = (input.display_attributes ?? [])
    .map((row) => ({
      attribute: row.attribute?.trim() ?? "",
      value: row.value?.trim() ?? "",
    }))
    .filter((row) => row.attribute !== "" && row.value !== "");

  if (normalized.length > 0) {
    return normalized;
  }

  return (input.attribute_values ?? [])
    .map((row) => {
      const attribute =
        row.attribute?.name?.trim() ||
        row.attribute_name?.trim() ||
        row.attribute?.slug?.trim() ||
        "";
      const value =
        row.display?.trim() ||
        row.option?.label?.trim() ||
        row.option?.value?.trim() ||
        row.option_value?.trim() ||
        row.value?.trim() ||
        "";
      if (!attribute || !value) {
        return null;
      }
      return { attribute, value };
    })
    .filter((row): row is VariantDisplayAttribute => row !== null);
}

export function mapVariantDisplayAttributesToSelected(
  attributes: VariantDisplayAttribute[],
): Array<{ name: string; value: string; slug?: string | null }> {
  return attributes.map((row) => ({
    name: row.attribute,
    value: row.value,
    slug: null,
  }));
}

export function formatVariantDisplayLabel(
  attributes: VariantDisplayAttribute[],
  fallback?: string | null,
): string {
  const fromAttributes = attributes.map((row) => row.value).filter(Boolean).join(" / ");
  return fromAttributes || fallback?.trim() || "";
}
