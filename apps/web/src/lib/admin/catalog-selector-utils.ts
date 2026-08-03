import type { AdminAsyncOption } from "@/components/admin/AdminAsyncSearchSelect";
import type { AdminCategory } from "@/lib/api/admin-catalog";

export type CategoryTreeSelection = {
  categoryId: string;
  subcategoryId: string;
};

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

    options.push({
      id: root.id,
      label: root.name,
      description: isOrphan ? "Subcategory" : "Category",
      indent: 0,
    });

    const children = categories
      .filter((item) => item.parentId === root.id)
      .sort((a, b) => a.name.localeCompare(b.name));

    for (const child of children) {
      options.push({
        id: child.id,
        label: `└── ${child.name}`,
        description: root.name,
        indent: 1,
      });
    }
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

  if (!selected.parentId) {
    return { categoryId: selected.id, subcategoryId: "" };
  }

  return {
    categoryId: selected.parentId,
    subcategoryId: selected.id,
  };
}

export function resolveCategoryTreeLabel(
  categories: AdminCategory[],
  categoryId: string,
  subcategoryId: string,
): string {
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
