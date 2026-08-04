import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  INLINE_ATTRIBUTE_OPTION_UX,
  canCreateCatalogAttributeOptions,
  findDuplicateAttributeOption,
  mergeCreatedAttributeOption,
  nextAttributeOptionSortOrder,
  normalizeAttributeOptionValue,
  selectOptionForGenerate,
  selectOptionForManualForm,
  toVariantAttributeOption,
} from "@/lib/admin/inline-attribute-option";
import type { AdminVariantAttribute } from "@/lib/api/admin-catalog";

const attributes: AdminVariantAttribute[] = [
  {
    catalogAttributeId: "color",
    name: "Color",
    slug: "color",
    type: "select",
    options: [
      { id: "blue", value: "Blue", slug: "blue" },
      { id: "red", value: "Red", slug: "red" },
    ],
  },
  {
    catalogAttributeId: "size",
    name: "Size",
    slug: "size",
    type: "select",
    options: [],
  },
];

describe("inline attribute option helpers", () => {
  it("stays inside the wizard via dialog UX", () => {
    assert.equal(INLINE_ATTRIBUTE_OPTION_UX.staysInsideWizard, true);
    assert.equal(INLINE_ATTRIBUTE_OPTION_UX.presentation, "dialog");
    assert.equal(INLINE_ATTRIBUTE_OPTION_UX.addNewLabel, "+ Add New");
  });

  it("gates create on configuration.manage", () => {
    assert.equal(canCreateCatalogAttributeOptions(undefined), true);
    assert.equal(canCreateCatalogAttributeOptions(["configuration.manage"]), true);
    assert.equal(canCreateCatalogAttributeOptions(["catalog.update"]), false);
  });

  it("detects case-insensitive duplicate values", () => {
    assert.equal(normalizeAttributeOptionValue("  Blue "), "blue");
    assert.equal(findDuplicateAttributeOption(attributes[0].options, "BLUE")?.value, "Blue");
    assert.equal(findDuplicateAttributeOption(attributes[0].options, " green "), undefined);
  });

  it("merges created option and auto-selects for generate + manual form", () => {
    const created = toVariantAttributeOption({
      id: "teal",
      attributeId: "color",
      value: "Teal",
      slug: "teal",
      sortOrder: 3,
    });

    const nextAttributes = mergeCreatedAttributeOption(attributes, "color", created);
    assert.equal(nextAttributes[0].options.length, 3);
    assert.equal(nextAttributes[0].options[2].id, "teal");

    const generateSelected = selectOptionForGenerate({ color: ["blue"] }, "color", "teal");
    assert.deepEqual(generateSelected.color, ["blue", "teal"]);

    const formSelection = selectOptionForManualForm({}, "color", "teal");
    assert.equal(formSelection.color, "teal");

    assert.equal(nextAttributeOptionSortOrder(attributes[0].options), 3);
    assert.equal(
      nextAttributeOptionSortOrder([
        { sortOrder: 1 },
        { sortOrder: 4 },
      ]),
      5,
    );
  });

  it("refreshes empty attribute options for generator without page reload", () => {
    const created = toVariantAttributeOption({
      id: "cotton",
      attributeId: "size",
      value: "Cotton",
      slug: "cotton",
      sortOrder: 1,
    });

    const next = mergeCreatedAttributeOption(attributes, "size", created);
    assert.equal(next[1].options.length, 1);
    assert.equal(next[1].options[0].value, "Cotton");

    const selected = selectOptionForGenerate({}, "size", "cotton");
    assert.deepEqual(selected.size, ["cotton"]);
  });
});
