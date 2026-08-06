import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  buildAttributeOptionApplyFormFields,
  countVariantsForAttributeOption,
  formatAttributeOptionApplySummary,
  pickDefaultAttributeForImageApply,
} from "@/lib/admin/variant-attribute-image-apply";
import type {
  AdminProductVariant,
  AdminVariantAttribute,
} from "@/lib/api/admin-catalog";

const attr = (
  partial: Partial<AdminVariantAttribute> &
    Pick<AdminVariantAttribute, "catalogAttributeId" | "name" | "slug">,
): AdminVariantAttribute => ({
  type: "select",
  options: partial.options ?? [
    { id: `${partial.slug}-opt`, value: "X", slug: "x" },
  ],
  ...partial,
});

const variant = (
  id: string,
  optionIds: string[],
): AdminProductVariant => ({
  id,
  productId: "p1",
  name: id,
  sku: id,
  barcode: null,
  status: "active",
  isActive: true,
  isDefault: false,
  sortOrder: 0,
  price: null,
  stock: null,
  pricesCount: 0,
  inventoriesCount: 0,
  commercialStocksCount: 0,
  hasActiveCommercialStock: false,
  displayAttributes: [],
  attributeValues: optionIds.map((optionId, index) => ({
    id: `${id}-av-${index}`,
    catalogAttributeId: "attr",
    attributeName: "Color",
    attributeSlug: "color",
    type: "select",
    optionId,
    optionValue: optionId,
    valueText: null,
    valueNumber: null,
    valueBoolean: null,
    display: optionId,
  })),
});

describe("variant attribute image apply helpers", () => {
  it("prefers Color attribute when available", () => {
    const size = attr({
      catalogAttributeId: "size",
      name: "Size",
      slug: "size",
    });
    const color = attr({
      catalogAttributeId: "color",
      name: "Color",
      slug: "color",
      options: [{ id: "blue", value: "Blue", slug: "blue" }],
    });

    assert.equal(pickDefaultAttributeForImageApply([size, color])?.slug, "color");
    assert.equal(pickDefaultAttributeForImageApply([size])?.slug, "size");
    assert.equal(pickDefaultAttributeForImageApply([]), null);
  });

  it("counts variants sharing an option id", () => {
    const variants = [
      variant("v1", ["blue", "s"]),
      variant("v2", ["blue", "m"]),
      variant("v3", ["red", "s"]),
    ];

    assert.equal(countVariantsForAttributeOption(variants, "blue"), 2);
    assert.equal(countVariantsForAttributeOption(variants, "red"), 1);
    assert.equal(countVariantsForAttributeOption(variants, ""), 0);
  });

  it("formats apply summary and form fields", () => {
    assert.equal(
      formatAttributeOptionApplySummary({
        optionValue: "Blue",
        attributeName: "Color",
        matchedCount: 3,
        appliedCount: 3,
        skippedCount: 0,
      }),
      "Applied image to 3 of 3 Color: Blue variants.",
    );

    assert.match(
      formatAttributeOptionApplySummary({
        optionValue: "Blue",
        attributeName: "Color",
        matchedCount: 3,
        appliedCount: 2,
        skippedCount: 1,
      }),
      /skipped — already had images/,
    );

    assert.match(
      formatAttributeOptionApplySummary({
        optionValue: "Blue",
        attributeName: null,
        matchedCount: 1,
        appliedCount: 0,
        skippedCount: 1,
      }),
      /No new images applied/,
    );

    assert.deepEqual(
      buildAttributeOptionApplyFormFields({
        catalogAttributeOptionId: "opt-1",
        altText: "Blue",
        title: "Blue image",
      }),
      {
        catalog_attribute_option_id: "opt-1",
        alt_text: "Blue",
        title: "Blue image",
      },
    );
  });
});
