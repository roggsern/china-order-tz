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

test("AdminCatalogProductWritePayload accepts simple product price", () => {
  const payload: AdminCatalogProductWritePayload = {
    name: "Pricing Phone",
    catalog_product_type_id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
    price: 175000,
    status: "draft",
  };

  assert.equal(payload.price, 175000);
});
