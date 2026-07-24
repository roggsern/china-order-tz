import assert from "node:assert/strict";
import { test } from "node:test";
import { mapAdminApiCatalogProduct } from "@/lib/api/admin-catalog";

test("mapAdminApiCatalogProduct maps legacy_configuration_product flag", () => {
  const legacy = mapAdminApiCatalogProduct({
    id: "prod-legacy",
    name: "Legacy Phone",
    slug: "legacy-phone",
    price: 1000,
    legacy_configuration_product: true,
  });

  assert.equal(legacy.legacyConfigurationProduct, true);

  const canonical = mapAdminApiCatalogProduct({
    id: "prod-canonical",
    name: "Canonical Phone",
    slug: "canonical-phone",
    price: 1000,
    legacy_configuration_product: false,
  });

  assert.equal(canonical.legacyConfigurationProduct, false);
});

test("mapAdminApiCatalogProduct defaults legacy_configuration_product to false", () => {
  const mapped = mapAdminApiCatalogProduct({
    id: "prod-simple",
    name: "Simple Phone",
    slug: "simple-phone",
    price: 500,
  });

  assert.equal(mapped.legacyConfigurationProduct, false);
});
