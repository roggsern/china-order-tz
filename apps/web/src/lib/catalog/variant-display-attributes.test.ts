import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  formatVariantDisplayLabel,
  mapVariantDisplayAttributes,
  mapVariantDisplayAttributesToSelected,
} from "./variant-display-attributes";

describe("mapVariantDisplayAttributes", () => {
  it("prefers normalized display_attributes", () => {
    const rows = mapVariantDisplayAttributes({
      display_attributes: [{ attribute: "Color", value: "Black" }],
      attribute_values: [
        {
          attribute: { name: "Color" },
          value: "Red",
        },
      ],
    });

    assert.deepEqual(rows, [{ attribute: "Color", value: "Black" }]);
  });

  it("falls back to nested attribute_values when display_attributes missing", () => {
    const rows = mapVariantDisplayAttributes({
      attribute_values: [
        {
          attribute: { name: "Size", slug: "size" },
          value: "Large",
        },
      ],
    });

    assert.deepEqual(rows, [{ attribute: "Size", value: "Large" }]);
  });

  it("maps storefront selectedAttributes shape", () => {
    assert.deepEqual(
      mapVariantDisplayAttributesToSelected([{ attribute: "Color", value: "Black" }]),
      [{ name: "Color", value: "Black", slug: null }],
    );
  });

  it("formats label from display values", () => {
    assert.equal(
      formatVariantDisplayLabel(
        [
          { attribute: "Color", value: "Black" },
          { attribute: "Size", value: "128GB" },
        ],
        "Fallback Name",
      ),
      "Black / 128GB",
    );
  });
});
