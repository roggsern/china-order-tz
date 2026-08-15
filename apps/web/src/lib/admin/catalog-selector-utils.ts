import type { AdminAsyncOption } from "@/components/admin/AdminAsyncSearchSelect";
import type { AdminCategory } from "@/lib/api/admin-catalog";

export type CategoryTreeSelection = {
  categoryId: string;
  subcategoryId: string;
};

/**
 * Whether a category is a valid product-classification leaf.
 * Prefers API `selectable` (CatalogLeafCategoryRules-aligned); falls back to
 * active + no active children present in the loaded payload.
 */
export function isSelectableCategoryLeaf(
  category: AdminCategory,
  categories: AdminCategory[],
): boolean {
  if (typeof category.selectable === "boolean") {
    return category.selectable;
  }

  if (!category.isActive) {
    return false;
  }

  if (typeof category.hasActiveChildren === "boolean") {
    return !category.hasActiveChildren;
  }

  return !categories.some(
    (candidate) => candidate.parentId === category.id && candidate.isActive,
  );
}

function prefixForDepth(depth: number): string {
  if (depth <= 0) {
    return "";
  }
  return `${"│  ".repeat(depth - 1)}└── `;
}

function appendSubtree(
  categories: AdminCategory[],
  parentId: string,
  depth: number,
  options: AdminAsyncOption[],
  parentName: string,
): void {
  const children = categories
    .filter((item) => item.parentId === parentId)
    .sort((a, b) => a.name.localeCompare(b.name));

  for (const child of children) {
    const selectable = isSelectableCategoryLeaf(child, categories);
    options.push({
      id: child.id,
      label: `${prefixForDepth(depth)}${child.name}`,
      description: selectable ? parentName : `${parentName} · navigate only`,
      indent: depth,
      disabled: !selectable,
    });
    appendSubtree(categories, child.id, depth + 1, options, child.name);
  }
}

/**
 * Build a recursive searchable category tree from a flat admin payload.
 * Structural nodes (inactive or with active children) stay visible but disabled.
 */
export function buildCategoryTreeOptions(
  categories: AdminCategory[],
): AdminAsyncOption[] {
  const categoryIds = new Set(categories.map((item) => item.id));

  const roots = categories
    .filter((item) => !item.parentId || !categoryIds.has(item.parentId))
    .sort((a, b) => a.name.localeCompare(b.name));

  const options: AdminAsyncOption[] = [];

  for (const root of roots) {
    const isOrphan = Boolean(root.parentId && !categoryIds.has(root.parentId));
    const selectable = isSelectableCategoryLeaf(root, categories);

    options.push({
      id: root.id,
      label: root.name,
      description: selectable
        ? isOrphan
          ? "Subcategory"
          : "Category"
        : isOrphan
          ? "Subcategory · navigate only"
          : "Category · navigate only",
      indent: 0,
      disabled: !selectable,
    });

    appendSubtree(categories, root.id, 1, options, root.name);
  }

  return options;
}

export function mapCategoryTreeSelection(
  categories: AdminCategory[],
  selectedId: string,
): CategoryTreeSelection {
  if (!selectedId) {
    return { categoryId: "", subcategoryId: "" };
  }

  const selected = categories.find((item) => item.id === selectedId);
  if (!selected) {
    return { categoryId: "", subcategoryId: "" };
  }

  if (!isSelectableCategoryLeaf(selected, categories)) {
    return { categoryId: "", subcategoryId: "" };
  }

  if (!selected.parentId) {
    return { categoryId: selected.id, subcategoryId: "" };
  }

  return {
    categoryId: selected.parentId,
    subcategoryId: selected.id,
  };
}

function categoryPathNames(
  categories: AdminCategory[],
  leafId: string,
): string[] {
  const names: string[] = [];
  let current = categories.find((item) => item.id === leafId);
  const guard = new Set<string>();

  while (current && !guard.has(current.id)) {
    guard.add(current.id);
    names.unshift(current.name);
    current = current.parentId
      ? categories.find((item) => item.id === current!.parentId)
      : undefined;
  }

  return names;
}

export function resolveCategoryTreeLabel(
  categories: AdminCategory[],
  categoryId: string,
  subcategoryId: string,
): string {
  const leafId = subcategoryId || categoryId;
  if (!leafId) {
    return "";
  }

  const path = categoryPathNames(categories, leafId);
  if (path.length > 0) {
    return path.join(" › ");
  }

  const parent = categories.find((item) => item.id === categoryId);
  const child = categories.find((item) => item.id === subcategoryId);

  if (parent && child) {
    return `${parent.name} › ${child.name}`;
  }
  if (child) {
    return child.name;
  }
  if (parent) {
    return parent.name;
  }
  return "";
}

export function resolveBrandLeafCategoryId(
  categoryId: string,
  subcategoryId: string,
): string | null {
  const leaf = subcategoryId || categoryId;
  return leaf.trim() ? leaf : null;
}
