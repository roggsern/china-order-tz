import assert from "node:assert/strict";
import { test } from "node:test";
import {
  mapAdminApiCatalogProduct,
  type AdminApiProduct,
  type AdminCatalogProductWritePayload,
} from "./admin-catalog";

test("mapAdminApiCatalogProduct maps base price for canonical admin reads", () => {
  const product: AdminApiProduct = {
    id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
    name: "Pricing Phone",
    slug: "pricing-phone",
    price: "175000.00",
    status: "draft",
    visibility: "public",
  };

  const mapped = mapAdminApiCatalogProduct(product);

  assert.equal(mapped.price, 175000);
});

test("mapAdminApiCatalogProduct maps purchase quantity rules as nullable integers", () => {
  const unrestricted: AdminApiProduct = {
    id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
    name: "Unrestricted Phone",
    slug: "unrestricted-phone",
    price: "175000.00",
    status: "draft",
    visibility: "public",
    minimum_order_quantity: null,
    order_increment: null,
  };

  const restricted: AdminApiProduct = {
    ...unrestricted,
    name: "Restricted Phone",
    slug: "restricted-phone",
    minimum_order_quantity: 6,
    order_increment: 3,
  };

  const unrestrictedMapped = mapAdminApiCatalogProduct(unrestricted);
  const restrictedMapped = mapAdminApiCatalogProduct(restricted);

  assert.equal(unrestrictedMapped.minimumOrderQuantity, null);
  assert.equal(unrestrictedMapped.orderIncrement, null);
  assert.equal(restrictedMapped.minimumOrderQuantity, 6);
  assert.equal(restrictedMapped.orderIncrement, 3);

  const omittedKeys = mapAdminApiCatalogProduct({
    id: unrestricted.id,
    name: "Omitted Keys Phone",
    slug: "omitted-keys-phone",
    price: "175000.00",
    status: "draft",
    visibility: "public",
  });
  assert.equal(omittedKeys.minimumOrderQuantity, null);
  assert.equal(omittedKeys.orderIncrement, null);
});

test("AdminCatalogProductWritePayload accepts simple product price", () => {
  const payload: AdminCatalogProductWritePayload = {
    name: "Pricing Phone",
    catalog_product_type_id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
    price: 175000,
    status: "draft",
  };

  assert.equal(payload.price, 175000);
});

test("AdminCatalogProductWritePayload accepts purchase quantity fields without volume tiers", () => {
  const payload: AdminCatalogProductWritePayload = {
    name: "Pricing Phone",
    catalog_product_type_id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
    price: 175000,
    status: "draft",
    minimum_order_quantity: 6,
    order_increment: 3,
  };

  assert.equal(payload.minimum_order_quantity, 6);
  assert.equal(payload.order_increment, 3);
  assert.equal("price_tiers" in payload, false);
});
