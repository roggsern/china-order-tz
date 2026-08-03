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
