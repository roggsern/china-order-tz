import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  buildCategoryTreeOptions,
  mapCategoryTreeSelection,
  resolveBrandLeafCategoryId,
  resolveCategoryTreeLabel,
} from "@/lib/admin/catalog-selector-utils";
import type { AdminCategory } from "@/lib/api/admin-catalog";

function category(
  id: string,
  name: string,
  parentId: string | null = null,
  overrides: Partial<AdminCategory> = {},
): AdminCategory {
  return {
    id,
    name,
    slug: name.toLowerCase().replace(/\s+/g, "-"),
    parentId,
    departmentId: "dept-1",
    origin: "china",
    isActive: true,
    ...overrides,
  } as AdminCategory;
}

describe("catalog selector utils", () => {
  const women = category("c1", "Women's Fashion");
  const blouse = category("c2", "Blouse", "c1");
  const dresses = category("c3", "Dresses", "c1");
  const phones = category("c4", "Phones");

  it("builds searchable tree options with parent/child clarity", () => {
    const options = buildCategoryTreeOptions([women, blouse, dresses, phones]);
    assert.deepEqual(
      options.map((option) => ({ id: option.id, indent: option.indent })),
      [
        { id: "c4", indent: 0 },
        { id: "c1", indent: 0 },
        { id: "c2", indent: 1 },
        { id: "c3", indent: 1 },
      ],
    );
    assert.match(options.find((option) => option.id === "c2")?.label ?? "", /Blouse/);
  });

  it("maps selection to category and subcategory ids", () => {
    assert.deepEqual(mapCategoryTreeSelection([women, blouse], "c1"), {
      categoryId: "c1",
      subcategoryId: "",
    });
    assert.deepEqual(mapCategoryTreeSelection([women, blouse], "c2"), {
      categoryId: "c1",
      subcategoryId: "c2",
    });
  });

  it("resolves selected label and brand leaf category", () => {
    assert.equal(
      resolveCategoryTreeLabel([women, blouse], "c1", "c2"),
      "Women's Fashion › Blouse",
    );
    assert.equal(resolveBrandLeafCategoryId("c1", "c2"), "c2");
    assert.equal(resolveBrandLeafCategoryId("c1", ""), "c1");
    assert.equal(resolveBrandLeafCategoryId("", ""), null);
  });

  it("preserves orphan active subcategories when parent is missing from response", () => {
    const inactiveParentId = "parent-missing";
    const tShirt = category("sub-1", "T-Shirts", inactiveParentId, {
      isActive: true,
    });

    const options = buildCategoryTreeOptions([tShirt]);

    assert.deepEqual(
      options.map((option) => ({ id: option.id, indent: option.indent, description: option.description })),
      [{ id: "sub-1", indent: 0, description: "Subcategory" }],
    );
    assert.deepEqual(mapCategoryTreeSelection([tShirt], "sub-1"), {
      categoryId: inactiveParentId,
      subcategoryId: "sub-1",
    });
  });

  it("renders inactive parent with active children for china department taxonomy", () => {
    const clothing = category("root-1", "Clothing", null, { isActive: false });
    const tShirt = category("sub-1", "T-Shirts", "root-1", { isActive: true });
    const polo = category("sub-2", "Polo Shirts", "root-1", { isActive: true });

    const options = buildCategoryTreeOptions([clothing, tShirt, polo]);

    assert.deepEqual(options.map((option) => option.id).slice(0, 1), ["root-1"]);
    assert.deepEqual(
      options.slice(1).map((option) => option.id).sort(),
      ["sub-1", "sub-2"],
    );
    assert.equal(options[0]?.description, "Category");
    assert.ok(options.some((option) => option.label.includes("T-Shirts")));
    assert.ok(options.some((option) => option.label.includes("Polo Shirts")));
  });

  it("keeps flat tz store categories unchanged", () => {
    const wigs = category("tz-1", "Wigs", null, {
      origin: "tz",
      storeId: "store-rovi",
      departmentId: null,
    });
    const makeup = category("tz-2", "Makeup", null, {
      origin: "tz",
      storeId: "store-rovi",
      departmentId: null,
    });

    const options = buildCategoryTreeOptions([wigs, makeup]);

    assert.deepEqual(
      options.map((option) => ({ id: option.id, indent: option.indent })),
      [
        { id: "tz-2", indent: 0 },
        { id: "tz-1", indent: 0 },
      ],
    );
  });
});
