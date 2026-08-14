import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  buildTaxonomyImportPayload,
  buildTaxonomyImportSummary,
  ensureTaxonomyAncestorsSelected,
  toggleTaxonomyImportSelection,
  type TaxonomyImportSourceNode,
} from "@/lib/admin/taxonomy-import";

const nodes: TaxonomyImportSourceNode[] = [
  {
    id: "tops",
    name: "Tops",
    slug: "tops",
    parentId: null,
    sortOrder: 1,
    productTypes: [],
  },
  {
    id: "blouses",
    name: "Blouses",
    slug: "blouses",
    parentId: "tops",
    sortOrder: 1,
    productTypes: [
      {
        id: "pt1",
        name: "Women's Blouse",
        attributesCount: 3,
        hasAttributeMappings: true,
      },
    ],
  },
  {
    id: "shirts",
    name: "Shirts",
    slug: "shirts",
    parentId: "tops",
    sortOrder: 2,
    productTypes: [],
  },
  {
    id: "bottoms",
    name: "Bottoms",
    slug: "bottoms",
    parentId: null,
    sortOrder: 2,
    productTypes: [],
  },
  {
    id: "skirts",
    name: "Skirts",
    slug: "skirts",
    parentId: "bottoms",
    sortOrder: 1,
    productTypes: [{ id: "pt2", name: "Skirt", attributesCount: 0, hasAttributeMappings: false }],
  },
];

describe("taxonomy-import helpers", () => {
  it("selecting a child ensures parent ancestors", () => {
    const selected = ensureTaxonomyAncestorsSelected({
      selectedIds: ["blouses"],
      nodes,
    });
    assert.deepEqual(new Set(selected), new Set(["blouses", "tops"]));
  });

  it("toggle on child selects parent; toggle off removes descendants", () => {
    const withChild = toggleTaxonomyImportSelection({
      selectedIds: [],
      nodes,
      nodeId: "blouses",
      checked: true,
    });
    assert.ok(withChild.includes("tops"));
    assert.ok(withChild.includes("blouses"));

    const afterParentOff = toggleTaxonomyImportSelection({
      selectedIds: ["tops", "blouses", "shirts"],
      nodes,
      nodeId: "tops",
      checked: false,
    });
    assert.deepEqual(afterParentOff, []);
  });

  it("builds import payload with attribute mappings gated by product types", () => {
    assert.deepEqual(
      buildTaxonomyImportPayload({
        departmentId: "dept-1",
        selectedIds: ["blouses", "tops"],
        includeProductTypes: true,
        includeAttributeMappings: true,
      }),
      {
        department_id: "dept-1",
        category_ids: ["blouses", "tops"],
        include_product_types: true,
        include_attribute_mappings: true,
      },
    );

    assert.equal(
      buildTaxonomyImportPayload({
        departmentId: "dept-1",
        selectedIds: ["blouses"],
        includeProductTypes: false,
        includeAttributeMappings: true,
      }).include_attribute_mappings,
      false,
    );
  });

  it("summarizes selected branches and product types", () => {
    const summary = buildTaxonomyImportSummary({
      selectedIds: ["tops", "blouses", "skirts", "bottoms"],
      nodes,
      includeProductTypes: true,
      includeAttributeMappings: true,
    });
    assert.equal(summary.categoryCount, 4);
    assert.equal(summary.productTypeCount, 2);
    assert.equal(summary.attributeMappedTypeCount, 1);
    assert.ok(summary.labels.includes("Blouses"));
  });
});
