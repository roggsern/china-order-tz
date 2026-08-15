import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  filterCatalogProductTypesByQuery,
  filterCatalogProductTypesForCategoryScope,
  formatCatalogProductTypeOptionDescription,
} from "@/lib/admin/catalog-product-type-scope";
import type { AdminCategory } from "@/lib/api/admin-catalog";

describe("catalog product type scope", () => {
  const categories: AdminCategory[] = [
    {
      id: "wigs-root",
      name: "Wigs",
      slug: "rovi-beauty-wigs",
      description: "",
      gradient: "",
      icon: "",
      parentId: null,
      storeId: "store-rovi",
      origin: "tz",
      isActive: true,
      sortOrder: 1,
      productsCount: 0,
    },
    {
      id: "lace-leaf",
      name: "Lace Front Wigs",
      slug: "rovi-beauty-lace-front-wigs",
      description: "",
      gradient: "",
      icon: "",
      parentId: "wigs-root",
      storeId: "store-rovi",
      origin: "tz",
      isActive: true,
      sortOrder: 1,
      productsCount: 0,
    },
  ];

  const productTypes = [
    { id: "type-wigs", subcategoryId: "wigs-root", name: "Wigs Type" },
    { id: "type-lace", subcategoryId: "lace-leaf", name: "Lace Type" },
    { id: "type-other-store", subcategoryId: "other-store-category", name: "Other Type" },
  ];

  it("shows Wigs product type when ROVI Wigs leaf category is selected alone", () => {
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

  it("shows only leaf product types when a TZ subcategory leaf is selected", () => {
    const matches = filterCatalogProductTypesForCategoryScope({
      productTypes,
      categoryId: "wigs-root",
      subcategoryId: "lace-leaf",
      categories,
    });

    assert.deepEqual(
      matches.map((type) => type.id),
      ["type-lace"],
    );
  });

  it("returns no product types when a structural parent is selected", () => {
    const matches = filterCatalogProductTypesForCategoryScope({
      productTypes,
      categoryId: "wigs-root",
      subcategoryId: "",
      categories,
    });

    assert.deepEqual(matches.map((type) => type.id), []);
  });

  it("scopes DC UPS leaf CPTs without parent Networking types", () => {
    const networking = {
      id: "np",
      name: "Networking & Power",
      slug: "computers-office-networking-power",
      description: "",
      gradient: "",
      icon: "",
      parentId: null,
      departmentId: "computers-office",
      origin: "china" as const,
      isActive: false,
      sortOrder: 1,
      productsCount: 0,
      selectable: false,
      hasActiveChildren: true,
    };
    const dcLeaf = {
      id: "dc",
      name: "DC UPS / Router Backup",
      slug: "computers-office-networking-power-dc-ups-router-backup",
      description: "",
      gradient: "",
      icon: "",
      parentId: "np",
      departmentId: "computers-office",
      origin: "china" as const,
      isActive: true,
      sortOrder: 1,
      productsCount: 0,
      selectable: true,
      hasActiveChildren: false,
    };

    const types = [
      { id: "mini", subcategoryId: "dc", name: "Mini DC UPS" },
      { id: "dc-ups", subcategoryId: "dc", name: "DC UPS" },
      { id: "other", subcategoryId: "np", name: "Should not appear" },
    ];

    const matches = filterCatalogProductTypesForCategoryScope({
      productTypes: types,
      categoryId: "np",
      subcategoryId: "dc",
      categories: [networking, dcLeaf],
    });

    assert.deepEqual(
      matches.map((type) => type.name).sort(),
      ["DC UPS", "Mini DC UPS"],
    );
  });

  it("preserves product type metadata such as name", () => {
    const matches = filterCatalogProductTypesForCategoryScope({
      productTypes,
      categoryId: "wigs-root",
      subcategoryId: "",
      categories: [categories[0]],
    });

    assert.equal(matches[0]?.name, "Wigs Type");
  });
});

describe("catalog product type search", () => {
  const searchableTypes = [
    {
      id: "ups-1",
      name: "DC Mini UPS",
      slug: "dc-mini-ups",
      categoryName: "Power",
      subcategoryName: "UPS",
    },
    {
      id: "phone-1",
      name: "Android Smartphone",
      slug: "android-smartphone",
      categoryName: "Phones",
      subcategoryName: "Smartphones",
    },
    {
      id: "phone-2",
      name: "iPhone",
      slug: "iphone",
      categoryName: "Phones",
      subcategoryName: "Smartphones",
    },
    {
      id: "camera-1",
      name: "Mirrorless Camera",
      slug: "mirrorless-camera",
      categoryName: "Cameras",
      subcategoryName: "Mirrorless",
    },
    {
      id: "dress-1",
      name: "Summer Dress",
      slug: "summer-dress",
      categoryName: "Apparel",
      subcategoryName: "Dresses",
    },
  ];

  it('search "UPS" returns DC Mini UPS', () => {
    const matches = filterCatalogProductTypesByQuery(searchableTypes, "UPS");
    assert.deepEqual(
      matches.map((type) => type.name),
      ["DC Mini UPS"],
    );
  });

  it('search "phone" returns smartphone types', () => {
    const matches = filterCatalogProductTypesByQuery(searchableTypes, "phone");
    assert.deepEqual(
      matches.map((type) => type.id).sort(),
      ["phone-1", "phone-2"].sort(),
    );
  });

  it("selecting a result resolves the matching product type id", () => {
    const matches = filterCatalogProductTypesByQuery(searchableTypes, "camera");
    assert.equal(matches.length, 1);
    const selectedId = matches[0]?.id ?? "";
    const selected = searchableTypes.find((type) => type.id === selectedId) ?? null;
    assert.equal(selected?.id, "camera-1");
    assert.equal(selected?.name, "Mirrorless Camera");
  });

  it("existing selected type remains available after edit reopen (empty query)", () => {
    const matches = filterCatalogProductTypesByQuery(searchableTypes, "");
    assert.equal(matches.length, searchableTypes.length);
    assert.ok(matches.some((type) => type.id === "ups-1" && type.name === "DC Mini UPS"));
  });

  it("formats category context for dropdown description", () => {
    assert.equal(
      formatCatalogProductTypeOptionDescription(searchableTypes[0]!),
      "Power · UPS",
    );
  });

  it("attributes mapping: search and select updates product type id", () => {
    let mappingTypeId = "";
    const selectType = (typeId: string) => {
      mappingTypeId = typeId;
    };

    const matches = filterCatalogProductTypesByQuery(searchableTypes, "Android");
    assert.equal(matches.length, 1);
    assert.equal(matches[0]?.name, "Android Smartphone");
    selectType(matches[0]!.id);
    assert.equal(mappingTypeId, "phone-1");

    const selected = searchableTypes.find((type) => type.id === mappingTypeId);
    assert.equal(selected?.name, "Android Smartphone");
  });
});
