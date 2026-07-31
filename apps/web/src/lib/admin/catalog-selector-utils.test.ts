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
): AdminCategory {
  return {
    id,
    name,
    slug: name.toLowerCase().replace(/\s+/g, "-"),
    parentId,
    departmentId: "dept-1",
    origin: "china",
    isActive: true,
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
});
