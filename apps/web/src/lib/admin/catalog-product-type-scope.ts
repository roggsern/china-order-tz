import type { AdminCategory } from "@/lib/api/admin-catalog";
import { isSelectableCategoryLeaf } from "@/lib/admin/catalog-selector-utils";

/** Category id plus every parent up the tree (leaf → root). */
export function categoryAncestorIds(
  categoryId: string,
  categories: AdminCategory[],
): Set<string> {
  const ids = new Set<string>();
  let current = categories.find((item) => item.id === categoryId);

  while (current) {
    ids.add(current.id);
    current = current.parentId
      ? categories.find((item) => item.id === current!.parentId)
      : undefined;
  }

  return ids;
}

export function productTypeMatchesCategoryScope(
  typeSubcategoryId: string,
  scopeCategoryId: string,
  categories: AdminCategory[],
): boolean {
  if (!scopeCategoryId.trim()) {
    return false;
  }

  // CPT must attach to the selected leaf itself (not an ancestor/descendant).
  return typeSubcategoryId === scopeCategoryId && categories.some((c) => c.id === scopeCategoryId);
}

/**
 * CPT choices for the resolved product classification leaf only.
 * Structural (non-leaf) selections yield no types.
 */
export function filterCatalogProductTypesForCategoryScope<T extends { id: string; subcategoryId: string }>(input: {
  productTypes: T[];
  categoryId: string;
  subcategoryId: string;
  categories: AdminCategory[];
}): T[] {
  const scopeId = input.subcategoryId || input.categoryId;
  if (!scopeId) {
    return [];
  }

  const scopeCategory = input.categories.find((category) => category.id === scopeId);
  if (!scopeCategory || !isSelectableCategoryLeaf(scopeCategory, input.categories)) {
    return [];
  }

  const allowedCategoryIds = new Set(input.categories.map((category) => category.id));

  return input.productTypes.filter((type) => {
    if (!allowedCategoryIds.has(type.subcategoryId)) {
      return false;
    }

    return type.subcategoryId === scopeId;
  });
}

export type CatalogProductTypeSearchable = {
  id: string;
  name: string;
  slug?: string | null;
  subcategoryName?: string | null;
  categoryName?: string | null;
  departmentName?: string | null;
};

/** Case-insensitive keyword match across type name and category context. */
export function filterCatalogProductTypesByQuery<T extends CatalogProductTypeSearchable>(
  productTypes: readonly T[],
  query: string,
): T[] {
  const normalized = query.trim().toLowerCase();
  if (!normalized) {
    return [...productTypes];
  }

  const tokens = normalized.split(/\s+/).filter(Boolean);

  return productTypes.filter((type) => {
    const haystack = [
      type.name,
      type.slug ?? "",
      type.subcategoryName ?? "",
      type.categoryName ?? "",
      type.departmentName ?? "",
    ]
      .join(" ")
      .toLowerCase();

    return tokens.every((token) => haystack.includes(token));
  });
}

export function formatCatalogProductTypeOptionDescription(
  type: CatalogProductTypeSearchable,
): string | undefined {
  const parts = [type.categoryName, type.subcategoryName].filter(
    (part): part is string => Boolean(part?.trim()),
  );
  if (parts.length === 0) {
    return type.departmentName?.trim() || undefined;
  }
  return parts.join(" · ");
}
