import assert from "node:assert/strict";
import { test } from "node:test";
import {
  mergeProductSupplierIdIntoPayload,
  shouldShowProductSupplierSelector,
  validateProductSupplierAssignment,
} from "./product-supplier-assignment";

test("shouldShowProductSupplierSelector for new china journey", () => {
  assert.equal(
    shouldShowProductSupplierSelector({
      isNewProduct: true,
      commerceJourney: "china",
      commerceChannelCode: null,
    }),
    true,
  );
  assert.equal(
    shouldShowProductSupplierSelector({
      isNewProduct: true,
      commerceJourney: "tz",
      commerceChannelCode: null,
    }),
    false,
  );
});

test("shouldShowProductSupplierSelector for existing CHINA_IMPORT product", () => {
  assert.equal(
    shouldShowProductSupplierSelector({
      isNewProduct: false,
      commerceJourney: "",
      commerceChannelCode: "CHINA_IMPORT",
    }),
    true,
  );
});

test("validateProductSupplierAssignment requires supplier for china products", () => {
  assert.equal(
    validateProductSupplierAssignment({
      isNewProduct: true,
      commerceJourney: "china",
      commerceChannelCode: null,
      supplierId: "",
    }),
    "Select a supplier for Order From China products.",
  );
  assert.equal(
    validateProductSupplierAssignment({
      isNewProduct: true,
      commerceJourney: "china",
      commerceChannelCode: null,
      supplierId: "",
      requireAssignment: false,
    }),
    null,
  );
  assert.equal(
    validateProductSupplierAssignment({
      isNewProduct: true,
      commerceJourney: "tz",
      commerceChannelCode: null,
      supplierId: "",
    }),
    null,
  );
});

test("mergeProductSupplierIdIntoPayload includes supplier_id for china products", () => {
  assert.deepEqual(
    mergeProductSupplierIdIntoPayload(
      { name: "Widget" },
      {
        isNewProduct: true,
        commerceJourney: "china",
        commerceChannelCode: null,
        supplierId: "supplier-1",
      },
    ),
    { name: "Widget", supplier_id: "supplier-1" },
  );
});
