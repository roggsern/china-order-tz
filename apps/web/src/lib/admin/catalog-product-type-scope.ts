import type { AdminCategory } from "@/lib/api/admin-catalog";

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

  return categoryAncestorIds(scopeCategoryId, categories).has(typeSubcategoryId);
}

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

  const allowedCategoryIds = new Set(input.categories.map((category) => category.id));
  const childCategoryIds = input.subcategoryId
    ? []
    : input.categories
        .filter((category) => category.parentId === scopeId)
        .map((category) => category.id);

  return input.productTypes.filter((type) => {
    if (!allowedCategoryIds.has(type.subcategoryId)) {
      return false;
    }

    if (type.subcategoryId === scopeId) {
      return true;
    }

    if (childCategoryIds.includes(type.subcategoryId)) {
      return true;
    }

    if (input.subcategoryId) {
      return productTypeMatchesCategoryScope(
        type.subcategoryId,
        input.subcategoryId,
        input.categories,
      );
    }

    return false;
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
