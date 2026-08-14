/**
 * Helpers for Admin Categories / store-catalog scoping (TZ_LOCAL).
 * Pure functions — no React, unit-tested.
 */

export type CatalogOriginFilter = "all" | "china" | "tz";

export type CategoriesPageScope = {
  origin: CatalogOriginFilter;
  storeId: string;
  departmentId: string;
};

export function parseCategoriesPageScope(
  params: URLSearchParams | { get(name: string): string | null },
): CategoriesPageScope {
  const rawOrigin = (params.get("origin") ?? "").trim().toLowerCase();
  const origin: CatalogOriginFilter =
    rawOrigin === "tz" || rawOrigin === "tanzania" || rawOrigin === "tz_local"
      ? "tz"
      : rawOrigin === "china"
        ? "china"
        : "all";

  return {
    origin,
    storeId: (params.get("store_id") ?? "").trim(),
    departmentId: (params.get("department_id") ?? "").trim(),
  };
}

export function buildStoreCategoriesHref(storeId: string): string {
  const id = storeId.trim();
  if (!id) {
    return "/admin/categories?origin=tz";
  }

  return `/admin/categories?origin=tz&store_id=${encodeURIComponent(id)}`;
}

export function buildSubcategoriesHref(input: {
  storeId?: string;
  origin?: "tz" | "china";
}): string {
  const params = new URLSearchParams();
  if (input.origin) {
    params.set("origin", input.origin);
  }
  if (input.storeId?.trim()) {
    params.set("store_id", input.storeId.trim());
  }
  const qs = params.toString();
  return qs ? `/admin/subcategories?${qs}` : "/admin/subcategories";
}

export function categoryFormRequiresDepartment(origin: "china" | "tz"): boolean {
  return origin === "china";
}

export function categoryFormRequiresStore(origin: "china" | "tz"): boolean {
  return origin === "tz";
}

export function validateCategoryFormDraft(input: {
  name: string;
  origin: "china" | "tz";
  departmentId: string;
  storeId: string;
}): string | null {
  if (!input.name.trim()) {
    return "Category name is required.";
  }
  if (categoryFormRequiresDepartment(input.origin) && !input.departmentId.trim()) {
    return "Department is required for China categories.";
  }
  if (categoryFormRequiresStore(input.origin) && !input.storeId.trim()) {
    return "Select a store for Tanzania store catalog categories.";
  }
  return null;
}

export function filterRootCategoriesForSubcategoryParent(input: {
  categories: Array<{
    id: string;
    parentId: string | null;
    departmentId: string | null;
    storeId: string | null;
    origin: "china" | "tz" | null;
  }>;
  origin: CatalogOriginFilter;
  storeId: string;
  departmentId: string;
}): Array<{ id: string; parentId: string | null; departmentId: string | null; storeId: string | null; origin: "china" | "tz" | null }> {
  return input.categories.filter((category) => {
    if (category.parentId) {
      return false;
    }
    if (input.origin === "tz") {
      if (category.origin !== "tz") {
        return false;
      }
      if (input.storeId && category.storeId !== input.storeId) {
        return false;
      }
      return Boolean(category.storeId);
    }
    if (input.origin === "china") {
      if (category.origin === "tz") {
        return false;
      }
      if (!category.departmentId) {
        return false;
      }
      if (input.departmentId && category.departmentId !== input.departmentId) {
        return false;
      }
      return true;
    }
    // all — china roots need department; tz roots need store
    if (category.origin === "tz") {
      return Boolean(category.storeId);
    }
    return Boolean(category.departmentId);
  });
}
