import assert from "node:assert/strict";
import { test } from "node:test";
import {
  mergeProductStoreIdIntoPayload,
  resolveProductStoreIdForReadiness,
  shouldShowProductStoreSelector,
  validateProductStoreAssignment,
} from "./product-store-assignment";

test("Case 1: TZ_LOCAL shows store selector for new and existing products", () => {
  assert.equal(
    shouldShowProductStoreSelector({
      isNewProduct: true,
      commerceJourney: "tz",
      commerceChannelCode: null,
    }),
    true,
  );
  assert.equal(
    shouldShowProductStoreSelector({
      isNewProduct: false,
      commerceJourney: "",
      commerceChannelCode: "TZ_LOCAL",
    }),
    true,
  );
});

test("Case 2: CHINA_IMPORT hides store selector", () => {
  assert.equal(
    shouldShowProductStoreSelector({
      isNewProduct: true,
      commerceJourney: "china",
      commerceChannelCode: null,
    }),
    false,
  );
  assert.equal(
    shouldShowProductStoreSelector({
      isNewProduct: false,
      commerceJourney: "",
      commerceChannelCode: "CHINA_IMPORT",
    }),
    false,
  );
});

test("Case 3: store selection is included in update payload for TZ_LOCAL", () => {
  const payload = mergeProductStoreIdIntoPayload(
    {
      name: "Local kettle",
      catalog_product_type_id: "cpt-1",
    },
    {
      isNewProduct: false,
      commerceJourney: "",
      commerceChannelCode: "TZ_LOCAL",
      storeId: "store-123",
    },
  );

  assert.equal(payload.store_id, "store-123");
});

test("mergeProductStoreIdIntoPayload omits store_id for CHINA_IMPORT updates", () => {
  const payload = mergeProductStoreIdIntoPayload(
    {
      name: "Imported kettle",
      catalog_product_type_id: "cpt-1",
    },
    {
      isNewProduct: false,
      commerceJourney: "",
      commerceChannelCode: "CHINA_IMPORT",
      storeId: "store-123",
    },
  );

  assert.equal("store_id" in payload, false);
});

test("validateProductStoreAssignment requires store for TZ_LOCAL", () => {
  assert.equal(
    validateProductStoreAssignment({
      isNewProduct: false,
      commerceJourney: "",
      commerceChannelCode: "TZ_LOCAL",
      storeId: "",
    }),
    "Select a store for Buy From Tanzania products.",
  );
  assert.equal(
    validateProductStoreAssignment({
      isNewProduct: false,
      commerceJourney: "",
      commerceChannelCode: "CHINA_IMPORT",
      storeId: "",
    }),
    null,
  );
});

test("resolveProductStoreIdForReadiness prefers form store over publish context", () => {
  assert.equal(
    resolveProductStoreIdForReadiness({
      formStoreId: "form-store",
      publishContextStoreId: "context-store",
      commerceChannelCode: "TZ_LOCAL",
      commerceJourney: "",
      isNewProduct: false,
    }),
    "form-store",
  );
});
