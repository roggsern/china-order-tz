import assert from "node:assert/strict";
import { test } from "node:test";
import {
  canRedirectLegacyProduct,
  detectWholesalePricingDependency,
  listLegacyEditSoftBlockers,
  mapAdminApiProductToLegacyEditPolicyProduct,
  type LegacyEditPolicyProduct,
} from "./legacy-edit-policy";

const SAFE_PRODUCT: LegacyEditPolicyProduct = {
  id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
  catalogProductTypeId: "cpt-1",
  legacyConfigurationProduct: false,
  hasProductPriceTiers: false,
  hasConfigurationPriceTiers: false,
};

test("Case 1: safe simple product => redirect true", () => {
  const result = canRedirectLegacyProduct(SAFE_PRODUCT);

  assert.deepEqual(result, { redirect: true, reason: "safe_for_canonical" });
});

test("Case 2: legacy configuration product => redirect false", () => {
  const result = canRedirectLegacyProduct({
    ...SAFE_PRODUCT,
    legacyConfigurationProduct: true,
  });

  assert.deepEqual(result, {
    redirect: false,
    reason: "legacy_configuration_product",
  });
});

test("Case 3: missing catalog product type => redirect false", () => {
  const result = canRedirectLegacyProduct({
    ...SAFE_PRODUCT,
    catalogProductTypeId: null,
  });

  assert.deepEqual(result, {
    redirect: false,
    reason: "missing_catalog_product_type",
  });
});

test("Case 4: product-level volume tiers stay canonical; configuration tiers stay legacy", () => {
  const productTiers = canRedirectLegacyProduct({
    ...SAFE_PRODUCT,
    hasProductPriceTiers: true,
  });
  assert.deepEqual(productTiers, { redirect: true, reason: "safe_for_canonical" });

  const configurationTiers = canRedirectLegacyProduct({
    ...SAFE_PRODUCT,
    hasConfigurationPriceTiers: true,
  });
  assert.deepEqual(configurationTiers, { redirect: false, reason: "wholesale_pricing" });
});

test("Case 5: soft blockers only still allow redirect (documented, not blocking)", () => {
  const product: LegacyEditPolicyProduct = {
    ...SAFE_PRODUCT,
    weight: 1.5,
    compareAtPrice: 99999,
    isDemo: true,
    lifecycleStatus: "out_of_stock",
    hasRichDescription: true,
  };

  const softBlockers = listLegacyEditSoftBlockers(product);
  assert.deepEqual(softBlockers, [
    "weight",
    "compare_at_price",
    "is_demo",
    "out_of_stock_lifecycle",
    "rich_text_description",
  ]);

  const result = canRedirectLegacyProduct(product);
  assert.deepEqual(result, { redirect: true, reason: "safe_for_canonical" });
});

test("detectWholesalePricingDependency reads product and configuration tiers from API shape", () => {
  const simple = detectWholesalePricingDependency({
    id: "p1",
    name: "Simple",
    slug: "simple",
    price: 1000,
    price_tiers: [{ min_quantity: 5, configuration_id: null }],
  });
  assert.equal(simple.hasProductPriceTiers, true);
  assert.equal(simple.hasConfigurationPriceTiers, false);

  const configured = detectWholesalePricingDependency({
    id: "p2",
    name: "Configured",
    slug: "configured",
    price: 1000,
    variants: [
      {
        id: "v1",
        price_tiers: [{ min_quantity: 10 }],
      },
    ],
  });
  assert.equal(configured.hasProductPriceTiers, false);
  assert.equal(configured.hasConfigurationPriceTiers, true);

  const mapped = mapAdminApiProductToLegacyEditPolicyProduct({
    id: "p3",
    name: "Mapped",
    slug: "mapped",
    price: 500,
    catalog_product_type_id: "cpt-9",
    legacy_configuration_product: false,
    price_tiers: [{ min_quantity: 2, configuration_id: "cfg-1" }],
  });
  assert.equal(mapped.hasConfigurationPriceTiers, true);
  assert.equal(mapped.hasProductPriceTiers, false);
});

test("uuid unresolved stays legacy", () => {
  assert.deepEqual(canRedirectLegacyProduct({ id: null }), {
    redirect: false,
    reason: "uuid_unresolved",
  });
});
