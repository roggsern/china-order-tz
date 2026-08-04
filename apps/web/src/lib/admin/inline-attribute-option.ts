import type {
  AdminVariantAttribute,
  AdminVariantAttributeOption,
} from "@/lib/api/admin-catalog";
import { hasAdminPermission } from "@/lib/api/admin-me";

/** Inline option create uses a dialog — never navigate away from the wizard. */
export const INLINE_ATTRIBUTE_OPTION_UX = {
  staysInsideWizard: true,
  presentation: "dialog" as const,
  addNewLabel: "+ Add New",
};

export function canCreateCatalogAttributeOptions(
  permissions: string[] | undefined,
): boolean {
  return hasAdminPermission(permissions, "configuration.manage");
}

export function normalizeAttributeOptionValue(value: string): string {
  return value.trim().toLowerCase();
}

export function findDuplicateAttributeOption(
  options: readonly { value: string }[],
  value: string,
): { value: string } | undefined {
  const normalized = normalizeAttributeOptionValue(value);
  if (normalized === "") {
    return undefined;
  }

  return options.find(
    (option) => normalizeAttributeOptionValue(option.value) === normalized,
  );
}

/**
 * Accepts catalog/variant option rows. `sortOrder` is optional because
 * AdminVariantAttributeOption only carries id/value/slug; missing values
 * fall back to 0 (same runtime as before).
 */
export function nextAttributeOptionSortOrder(
  options: ReadonlyArray<{ sortOrder?: number; value?: string }>,
): number {
  if (options.length === 0) {
    return 1;
  }

  const maxExplicit = Math.max(
    ...options.map((option) => Number(option.sortOrder ?? 0)),
  );

  return Math.max(maxExplicit, options.length) + 1;
}

/** Merge a newly created option into local attribute state (immediate UI refresh). */
export function mergeCreatedAttributeOption(
  attributes: readonly AdminVariantAttribute[],
  attributeId: string,
  option: AdminVariantAttributeOption,
): AdminVariantAttribute[] {
  return attributes.map((attribute) => {
    if (attribute.catalogAttributeId !== attributeId) {
      return attribute;
    }

    if (attribute.options.some((row) => row.id === option.id)) {
      return attribute;
    }

    return {
      ...attribute,
      options: [...attribute.options, option],
    };
  });
}

/** Auto-select for Generate Variants checkboxes. */
export function selectOptionForGenerate(
  generateSelected: Record<string, string[]>,
  attributeId: string,
  optionId: string,
): Record<string, string[]> {
  const current = generateSelected[attributeId] ?? [];
  if (current.includes(optionId)) {
    return generateSelected;
  }

  return {
    ...generateSelected,
    [attributeId]: [...current, optionId],
  };
}

/** Auto-select for the manual create/edit variant dropdown. */
export function selectOptionForManualForm(
  optionByAttribute: Record<string, string>,
  attributeId: string,
  optionId: string,
): Record<string, string> {
  return {
    ...optionByAttribute,
    [attributeId]: optionId,
  };
}

export function toVariantAttributeOption(option: {
  id: string;
  attributeId: string;
  value: string;
  slug: string;
  sortOrder: number;
}): AdminVariantAttributeOption {
  return {
    id: option.id,
    value: option.value,
    slug: option.slug,
  };
}
