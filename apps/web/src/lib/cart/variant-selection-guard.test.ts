import assert from "node:assert/strict";
import { test } from "node:test";
import {
  rejectNullConfigurationForVariantPathProduct,
  VARIANT_SELECTION_REQUIRED_MESSAGE,
} from "./variant-selection-guard";

test("rejects known variant-path product with null configuration", () => {
  assert.equal(
    rejectNullConfigurationForVariantPathProduct(
      { requiresVariantSelection: true },
      null,
    ),
    VARIANT_SELECTION_REQUIRED_MESSAGE,
  );
});

test("rejects known variant-path product with empty configuration", () => {
  assert.equal(
    rejectNullConfigurationForVariantPathProduct(
      { requiresVariantSelection: true },
      "   ",
    ),
    VARIANT_SELECTION_REQUIRED_MESSAGE,
  );
});

test("allows variant-path product with concrete configuration", () => {
  assert.equal(
    rejectNullConfigurationForVariantPathProduct(
      { requiresVariantSelection: true },
      "019f-config-id",
    ),
    null,
  );
});

test("does not guess when requiresVariantSelection is undefined", () => {
  assert.equal(
    rejectNullConfigurationForVariantPathProduct({}, null),
    null,
  );
});

test("allows simple products without configuration", () => {
  assert.equal(
    rejectNullConfigurationForVariantPathProduct(
      { requiresVariantSelection: false },
      null,
    ),
    null,
  );
});
