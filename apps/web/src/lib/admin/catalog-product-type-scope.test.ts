import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { filterCatalogProductTypesForCategoryScope } from "@/lib/admin/catalog-product-type-scope";
import type { AdminCategory } from "@/lib/api/admin-catalog";

describe("catalog product type scope", () => {
  const categories: AdminCategory[] = [
    {
      id: "wigs-root",
      name: "Wigs",
      slug: "rovi-beauty-wigs",
      parentId: null,
      storeId: "store-rovi",
      origin: "tz",
      isActive: true,
      sortOrder: 1,
    },
    {
      id: "lace-leaf",
      name: "Lace Front Wigs",
      slug: "rovi-beauty-lace-front-wigs",
      parentId: "wigs-root",
      storeId: "store-rovi",
      origin: "tz",
      isActive: true,
      sortOrder: 1,
    },
  ];

  const productTypes = [
    { id: "type-wigs", subcategoryId: "wigs-root" },
    { id: "type-lace", subcategoryId: "lace-leaf" },
    { id: "type-other-store", subcategoryId: "other-store-category" },
  ];

  it("shows Wigs product type when ROVI Wigs category is selected", () => {
    const matches = filterCatalogProductTypesForCategoryScope({
      productTypes,
      categoryId: "wigs-root",
      subcategoryId: "",
      categories: [categories[0]],
    });

    assert.deepEqual(
      matches.map((type) => type.id),
      ["type-wigs"],
    );
  });

  it("shows leaf product types when a TZ subcategory is selected", () => {
    const matches = filterCatalogProductTypesForCategoryScope({
      productTypes,
      categoryId: "wigs-root",
      subcategoryId: "lace-leaf",
      categories,
    });

    assert.deepEqual(
      matches.map((type) => type.id).sort(),
      ["type-lace", "type-wigs"].sort(),
    );
  });

  it("includes child product types when parent category is selected", () => {
    const matches = filterCatalogProductTypesForCategoryScope({
      productTypes,
      categoryId: "wigs-root",
      subcategoryId: "",
      categories,
    });

    assert.deepEqual(
      matches.map((type) => type.id).sort(),
      ["type-lace", "type-wigs"].sort(),
    );
  });
});
