import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  buildCategoryTreeOptions,
  CATEGORY_META_SEPARATOR,
  CATEGORY_PATH_SEPARATOR,
  filterProductClassificationCategories,
  isSelectableCategoryLeaf,
  mapCategoryTreeSelection,
  resolveBrandLeafCategoryId,
  resolveCategoryTreeLabel,
} from "@/lib/admin/catalog-selector-utils";
import type { AdminCategory } from "@/lib/api/admin-catalog";

const MOJIBAKE_MARKERS = [/â€/, /Â·/, /â”/, /â‚/, /Ã/];

function assertNoMojibake(value: string): void {
  for (const marker of MOJIBAKE_MARKERS) {
    assert.equal(
      marker.test(value),
      false,
      `Unexpected mojibake in: ${JSON.stringify(value)}`,
    );
  }
}

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
      options.map((option) => ({ id: option.id, indent: option.indent, disabled: option.disabled })),
      [
        { id: "c4", indent: 0, disabled: false },
        { id: "c1", indent: 0, disabled: true },
        { id: "c2", indent: 1, disabled: false },
        { id: "c3", indent: 1, disabled: false },
      ],
    );
    assert.equal(options.find((option) => option.id === "c2")?.label, "Blouse");
  });

  it("maps selection to category and subcategory ids for leaves only", () => {
    assert.deepEqual(mapCategoryTreeSelection([women, blouse], "c1"), {
      categoryId: "",
      subcategoryId: "",
    });
    assert.deepEqual(mapCategoryTreeSelection([women, blouse], "c2"), {
      categoryId: "c1",
      subcategoryId: "c2",
    });
  });

  it("resolves selected label and brand leaf category with ASCII path separators", () => {
    assert.equal(CATEGORY_PATH_SEPARATOR, " > ");
    assert.equal(
      resolveCategoryTreeLabel([women, blouse], "c1", "c2"),
      "Women's Fashion > Blouse",
    );
    assertNoMojibake(resolveCategoryTreeLabel([women, blouse], "c1", "c2"));
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
      options.map((option) => ({
        id: option.id,
        indent: option.indent,
        description: option.description,
        disabled: option.disabled,
      })),
      [{ id: "sub-1", indent: 0, description: "Subcategory", disabled: false }],
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

    assert.equal(options[0]?.id, "root-1");
    assert.equal(options[0]?.disabled, true);
    assert.equal(options[0]?.description, `Category${CATEGORY_META_SEPARATOR}navigate only`);
    assert.deepEqual(
      options.slice(1).map((option) => option.id).sort(),
      ["sub-1", "sub-2"],
    );
    assert.ok(options.slice(1).every((option) => option.disabled === false));
    assertNoMojibake(options[0]?.description ?? "");
  });

  it("keeps flat tz store categories unchanged and selectable", () => {
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
      options.map((option) => ({ id: option.id, indent: option.indent, disabled: option.disabled })),
      [
        { id: "tz-2", indent: 0, disabled: false },
        { id: "tz-1", indent: 0, disabled: false },
      ],
    );
  });

  it("exposes Networking & Power leaves as selectable under disabled structural parent", () => {
    const parent = category("np", "Networking & Power", null, { isActive: false });
    const ups = category("ups", "UPS & Backup Power", "np");
    const dcUps = category("dc", "DC UPS / Router Backup", "np");
    const routers = category("rt", "Routers & Networking", "np");
    const psu = category("ps", "Power Supplies", "np");

    const options = buildCategoryTreeOptions([parent, ups, dcUps, routers, psu]);
    assert.equal(options.find((o) => o.id === "np")?.disabled, true);
    assert.equal(options.find((o) => o.id === "dc")?.label, "DC UPS / Router Backup");
    assert.deepEqual(
      options.filter((o) => o.indent === 1).map((o) => o.id).sort(),
      ["dc", "ps", "rt", "ups"],
    );
    assert.ok(options.filter((o) => o.indent === 1).every((o) => o.disabled === false));
    assert.deepEqual(mapCategoryTreeSelection([parent, ups, dcUps, routers, psu], "dc"), {
      categoryId: "np",
      subcategoryId: "dc",
    });
    assert.deepEqual(mapCategoryTreeSelection([parent, ups, dcUps, routers, psu], "np"), {
      categoryId: "",
      subcategoryId: "",
    });
    assert.equal(
      resolveCategoryTreeLabel([parent, ups, dcUps, routers, psu], "np", "dc"),
      "Networking & Power > DC UPS / Router Backup",
    );
    for (const option of options) {
      assertNoMojibake(option.label);
      assertNoMojibake(option.description ?? "");
    }
  });

  it("supports arbitrary depth beyond two levels without unicode tree prefixes", () => {
    const root = category("r", "Root Structural", null, { isActive: false });
    const mid = category("m", "Mid Structural", "r", { isActive: false });
    const leaf = category("l", "Deep Leaf", "m", { isActive: true });

    const options = buildCategoryTreeOptions([root, mid, leaf]);
    assert.deepEqual(
      options.map((o) => ({ id: o.id, indent: o.indent, disabled: o.disabled, label: o.label })),
      [
        { id: "r", indent: 0, disabled: true, label: "Root Structural" },
        { id: "m", indent: 1, disabled: true, label: "Mid Structural" },
        { id: "l", indent: 2, disabled: false, label: "Deep Leaf" },
      ],
    );
    assert.deepEqual(mapCategoryTreeSelection([root, mid, leaf], "l"), {
      categoryId: "m",
      subcategoryId: "l",
    });
    assert.equal(
      resolveCategoryTreeLabel([root, mid, leaf], "m", "l"),
      "Root Structural > Mid Structural > Deep Leaf",
    );
    assertNoMojibake(resolveCategoryTreeLabel([root, mid, leaf], "m", "l"));
  });

  it("honors API selectable / hasActiveChildren over local inference", () => {
    const parent = category("p", "Parent", null, {
      isActive: true,
      selectable: false,
      hasActiveChildren: true,
    });
    const child = category("c", "Child", "p", {
      isActive: true,
      selectable: true,
      hasActiveChildren: false,
    });

    assert.equal(isSelectableCategoryLeaf(parent, [parent, child]), false);
    assert.equal(isSelectableCategoryLeaf(child, [parent, child]), true);
    assert.equal(buildCategoryTreeOptions([parent, child]).find((o) => o.id === "p")?.disabled, true);
  });

  it("treats inactive leaves as non-selectable", () => {
    const legacy = category("legacy", "Legacy Chargers", null, { isActive: false });
    const options = buildCategoryTreeOptions([legacy]);
    assert.equal(options[0]?.disabled, true);
    assert.deepEqual(mapCategoryTreeSelection([legacy], "legacy"), {
      categoryId: "",
      subcategoryId: "",
    });
  });

  it("filters Catalog Bible chrome out of China product classification categories", () => {
    const bible = category("bible", "Electronics", null, {
      departmentId: null,
      isActive: true,
    });
    const operational = category("ops", "Networking & Power", null, {
      departmentId: "computers-office",
      isActive: false,
    });
    const leaf = category("leaf", "DC UPS", "ops", {
      departmentId: "computers-office",
      isActive: true,
    });

    const filtered = filterProductClassificationCategories(
      [bible, operational, leaf],
      "china",
    );

    assert.deepEqual(
      filtered.map((item) => item.id).sort(),
      ["leaf", "ops"],
    );
  });
});
