/**
 * Pure helpers for TZ “Add from existing taxonomy” selection UX.
 */

export type TaxonomyImportSourceNode = {
  id: string;
  name: string;
  slug: string;
  parentId: string | null;
  sortOrder: number;
  isActive: boolean;
  importable: boolean;
  productTypes: Array<{
    id: string;
    name: string;
    attributesCount: number;
    hasAttributeMappings: boolean;
  }>;
};

export function taxonomyNodeProductTypeLabel(node: TaxonomyImportSourceNode): string {
  if (node.productTypes.length > 0) {
    return `${node.productTypes.length} product type${node.productTypes.length === 1 ? "" : "s"}`;
  }
  return "No source Product Type available";
}

/** When a child is selected, ensure all ancestors are also selected. */
export function ensureTaxonomyAncestorsSelected(input: {
  selectedIds: string[];
  nodes: TaxonomyImportSourceNode[];
}): string[] {
  const byId = new Map(input.nodes.map((node) => [node.id, node]));
  const next = new Set(input.selectedIds);

  for (const id of input.selectedIds) {
    let current = byId.get(id);
    let guard = 0;
    while (current?.parentId && guard++ < 100) {
      next.add(current.parentId);
      current = byId.get(current.parentId);
    }
  }

  return [...next];
}

/** Toggle a node; selecting a child also selects ancestors. */
export function toggleTaxonomyImportSelection(input: {
  selectedIds: string[];
  nodes: TaxonomyImportSourceNode[];
  nodeId: string;
  checked: boolean;
}): string[] {
  const byId = new Map(input.nodes.map((node) => [node.id, node]));
  const next = new Set(input.selectedIds);

  if (input.checked) {
    next.add(input.nodeId);
    let current = byId.get(input.nodeId);
    let guard = 0;
    while (current?.parentId && guard++ < 100) {
      next.add(current.parentId);
      current = byId.get(current.parentId);
    }
    return [...next];
  }

  // Uncheck node and all descendants
  const toRemove = new Set<string>([input.nodeId]);
  let changed = true;
  while (changed) {
    changed = false;
    for (const node of input.nodes) {
      if (node.parentId && toRemove.has(node.parentId) && !toRemove.has(node.id)) {
        toRemove.add(node.id);
        changed = true;
      }
    }
  }
  for (const id of toRemove) {
    next.delete(id);
  }
  return [...next];
}

export function buildTaxonomyImportSummary(input: {
  selectedIds: string[];
  nodes: TaxonomyImportSourceNode[];
  includeProductTypes: boolean;
  includeAttributeMappings: boolean;
}): {
  categoryCount: number;
  productTypeCount: number;
  attributeMappedTypeCount: number;
  leavesWithoutProductTypes: number;
  labels: string[];
} {
  const selected = input.nodes.filter((node) => input.selectedIds.includes(node.id));
  const labels = selected
    .slice()
    .sort((a, b) => a.sortOrder - b.sortOrder || a.name.localeCompare(b.name))
    .map((node) => node.name);

  let productTypeCount = 0;
  let attributeMappedTypeCount = 0;
  let leavesWithoutProductTypes = 0;
  if (input.includeProductTypes) {
    for (const node of selected) {
      productTypeCount += node.productTypes.length;
      if (node.productTypes.length === 0) {
        leavesWithoutProductTypes += 1;
      }
      if (input.includeAttributeMappings) {
        attributeMappedTypeCount += node.productTypes.filter(
          (type) => type.hasAttributeMappings || type.attributesCount > 0,
        ).length;
      }
    }
  }

  return {
    categoryCount: selected.length,
    productTypeCount,
    attributeMappedTypeCount,
    leavesWithoutProductTypes,
    labels,
  };
}

export function buildTaxonomyImportPayload(input: {
  departmentId: string;
  selectedIds: string[];
  includeProductTypes: boolean;
  includeAttributeMappings: boolean;
}): {
  department_id: string;
  category_ids: string[];
  include_product_types: boolean;
  include_attribute_mappings: boolean;
} {
  return {
    department_id: input.departmentId,
    category_ids: [...input.selectedIds],
    include_product_types: input.includeProductTypes,
    include_attribute_mappings:
      input.includeProductTypes && input.includeAttributeMappings,
  };
}
