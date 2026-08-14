import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  buildStoreCategoriesHref,
  buildSubcategoriesHref,
  categoryFormRequiresDepartment,
  categoryFormRequiresStore,
  filterRootCategoriesForSubcategoryParent,
  parseCategoriesPageScope,
  validateCategoryFormDraft,
} from "@/lib/admin/tz-store-categories";

describe("tz-store-categories helpers", () => {
  it("parses origin=tz and store_id query scope", () => {
    const scope = parseCategoriesPageScope(
      new URLSearchParams("origin=tz&store_id=store-zion"),
    );
    assert.equal(scope.origin, "tz");
    assert.equal(scope.storeId, "store-zion");
  });

  it("builds manage-store-categories href", () => {
    assert.equal(
      buildStoreCategoriesHref("abc-123"),
      "/admin/categories?origin=tz&store_id=abc-123",
    );
  });

  it("builds subcategories href with store scope", () => {
    assert.equal(
      buildSubcategoriesHref({ origin: "tz", storeId: "s1" }),
      "/admin/subcategories?origin=tz&store_id=s1",
    );
  });

  it("requires department only for china and store only for tz", () => {
    assert.equal(categoryFormRequiresDepartment("china"), true);
    assert.equal(categoryFormRequiresDepartment("tz"), false);
    assert.equal(categoryFormRequiresStore("tz"), true);
    assert.equal(categoryFormRequiresStore("china"), false);
  });

  it("validates tz create without department when store is set", () => {
    assert.equal(
      validateCategoryFormDraft({
        name: "Tops",
        origin: "tz",
        departmentId: "",
        storeId: "zion",
      }),
      null,
    );
  });

  it("rejects tz create without store", () => {
    assert.equal(
      validateCategoryFormDraft({
        name: "Tops",
        origin: "tz",
        departmentId: "",
        storeId: "",
      }),
      "Select a store for Tanzania store catalog categories.",
    );
  });

  it("rejects china create without department", () => {
    assert.equal(
      validateCategoryFormDraft({
        name: "Phones",
        origin: "china",
        departmentId: "",
        storeId: "",
      }),
      "Department is required for China categories.",
    );
  });

  it("scopes subcategory parents to selected tz store", () => {
    const roots = filterRootCategoriesForSubcategoryParent({
      origin: "tz",
      storeId: "zion",
      departmentId: "",
      categories: [
        {
          id: "1",
          parentId: null,
          departmentId: null,
          storeId: "zion",
          origin: "tz",
        },
        {
          id: "2",
          parentId: null,
          departmentId: null,
          storeId: "rovi",
          origin: "tz",
        },
        {
          id: "3",
          parentId: null,
          departmentId: "dept",
          storeId: null,
          origin: "china",
        },
        {
          id: "4",
          parentId: "1",
          departmentId: null,
          storeId: "zion",
          origin: "tz",
        },
      ],
    });
    assert.deepEqual(
      roots.map((row) => row.id),
      ["1"],
    );
  });
});
