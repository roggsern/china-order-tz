import assert from "node:assert/strict";
import { test } from "node:test";
import {
  buildAdminCatalogLifecycleWritePayload,
  mapAdminApiCatalogProduct,
  type AdminApiProduct,
} from "./admin-catalog";

test("buildAdminCatalogLifecycleWritePayload sends status without is_active", () => {
  const payload = buildAdminCatalogLifecycleWritePayload({
    name: "Lifecycle Phone",
    catalog_product_type_id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
    status: "active",
  });

  assert.equal(payload.status, "active");
  assert.equal("is_active" in payload, false);
});

test("mapAdminApiCatalogProduct prefers lifecycle_status over is_active mismatch", () => {
  const product: AdminApiProduct = {
    id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
    name: "Lifecycle Phone",
    slug: "lifecycle-phone",
    price: 0,
    lifecycle_status: "draft",
    is_active: true,
  };

  const mapped = mapAdminApiCatalogProduct(product);

  assert.equal(mapped.status, "draft");
});

test("buildAdminCatalogLifecycleWritePayload supports archived transition", () => {
  const payload = buildAdminCatalogLifecycleWritePayload({
    name: "Lifecycle Phone",
    catalog_product_type_id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
    status: "archived",
  });

  assert.equal(payload.status, "archived");
  assert.equal("is_active" in payload, false);
});
