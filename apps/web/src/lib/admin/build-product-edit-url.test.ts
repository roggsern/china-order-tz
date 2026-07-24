import assert from "node:assert/strict";
import { test } from "node:test";
import {
  buildAdminProductEditUrl,
  legacyEditPolicyFromCatalogProduct,
  legacyEditPolicyFromLegacyProduct,
  resolveAdminProductEditUrl,
} from "./build-product-edit-url";
import type { AdminCatalogProduct } from "@/lib/api/admin-catalog";
import { legacyNumericIdFromCatalogProductId } from "./product-id-map";

const CATALOG_UUID = "019f7a6e-4d46-7376-aca4-aed79f33519b";
const LEGACY_NUMERIC_ID = legacyNumericIdFromCatalogProductId(CATALOG_UUID);

const COMPLETE_SAFE_LINK = {
  legacyNumericId: LEGACY_NUMERIC_ID,
  id: CATALOG_UUID,
  catalogProductTypeId: "cpt-1",
  legacyConfigurationProduct: false,
  hasProductPriceTiers: false,
  hasConfigurationPriceTiers: false,
};

function completeLegacyProduct(
  overrides: Partial<Parameters<typeof legacyEditPolicyFromLegacyProduct>[0]> = {},
) {
  return legacyEditPolicyFromLegacyProduct({
    id: LEGACY_NUMERIC_ID,
    catalogProductId: CATALOG_UUID,
    catalogProductTypeId: "cpt-1",
    legacyConfigurationProduct: false,
    name: "Phone",
    slug: "phone",
    description: "Phone",
    price: 1000,
    oldPrice: 0,
    rating: 0,
    reviews: 0,
    badge: "",
    badges: [],
    trustBadges: [],
    origin: "china",
    gradient: "from-zinc-500 to-zinc-700",
    emoji: "📦",
    categorySlug: "phones",
    stock: 1,
    images: [],
    features: [],
    specifications: [],
    customerReviews: [],
    featured: false,
    status: "active",
    priceTiers: [],
    configurations: [],
    ...overrides,
  });
}

test("Case 1: complete safe product => canonical URL", () => {
  const decision = resolveAdminProductEditUrl(COMPLETE_SAFE_LINK);

  assert.equal(decision.reason, "safe_for_canonical");
  assert.equal(
    decision.url,
    `/admin/products?edit=${encodeURIComponent(CATALOG_UUID)}`,
  );
  assert.equal(buildAdminProductEditUrl(COMPLETE_SAFE_LINK), decision.url);
  assert.equal(
    buildAdminProductEditUrl(completeLegacyProduct()),
    `/admin/products?edit=${encodeURIComponent(CATALOG_UUID)}`,
  );
});

test("Case 2: AdminCatalogProduct missing policy fields => legacy URL", () => {
  const catalogProduct: AdminCatalogProduct = {
    id: CATALOG_UUID,
    name: "Catalog Phone",
    slug: "catalog-phone",
    sku: null,
    price: 1000,
    shortDescription: "",
    description: "",
    status: "draft",
    visibility: "public",
    isActive: false,
    isFeatured: false,
    sortOrder: 0,
    brandId: null,
    brandName: null,
    catalogProductTypeId: "cpt-1",
    catalogProductTypeName: null,
    categoryId: null,
    categoryName: null,
    departmentId: null,
    commerceChannelId: null,
    commerceChannelCode: null,
    storeId: null,
    hasSimpleInventoryPolicy: false,
    legacyConfigurationProduct: false,
    isDemo: false,
    deletedAt: null,
  };

  const link = legacyEditPolicyFromCatalogProduct(catalogProduct);
  assert.equal(link.hasProductPriceTiers, undefined);
  assert.equal(link.hasConfigurationPriceTiers, undefined);

  const decision = resolveAdminProductEditUrl(catalogProduct);
  assert.equal(decision.reason, "incomplete_product_context");
  assert.equal(decision.url, `/admin/products/${LEGACY_NUMERIC_ID}/edit`);
});

test("Case 3: missing legacy flag => legacy URL", () => {
  const incomplete = completeLegacyProduct({ legacyConfigurationProduct: undefined });
  const mapped = legacyEditPolicyFromLegacyProduct({
    id: LEGACY_NUMERIC_ID,
    catalogProductId: CATALOG_UUID,
    catalogProductTypeId: "cpt-1",
    name: "Phone",
    slug: "phone",
    description: "Phone",
    price: 1000,
    oldPrice: 0,
    rating: 0,
    reviews: 0,
    badge: "",
    badges: [],
    trustBadges: [],
    origin: "china",
    gradient: "from-zinc-500 to-zinc-700",
    emoji: "📦",
    categorySlug: "phones",
    stock: 1,
    images: [],
    features: [],
    specifications: [],
    customerReviews: [],
    featured: false,
    status: "active",
    priceTiers: [],
    configurations: [],
  });

  assert.equal(mapped.legacyConfigurationProduct, undefined);

  const decision = resolveAdminProductEditUrl(incomplete);
  assert.equal(decision.reason, "incomplete_product_context");
  assert.equal(decision.url, `/admin/products/${LEGACY_NUMERIC_ID}/edit`);
});

test("Case 4: missing catalog UUID => legacy URL", () => {
  const decision = resolveAdminProductEditUrl({
    ...COMPLETE_SAFE_LINK,
    id: null,
  });

  assert.equal(decision.reason, "incomplete_product_context");
  assert.equal(decision.url, `/admin/products/${LEGACY_NUMERIC_ID}/edit`);

  const fromProduct = resolveAdminProductEditUrl(
    completeLegacyProduct({ catalogProductId: undefined }),
  );
  assert.equal(fromProduct.reason, "incomplete_product_context");
  assert.equal(fromProduct.url, `/admin/products/${LEGACY_NUMERIC_ID}/edit`);
});

test("known policy blockers still stay legacy after complete context", () => {
  assert.equal(
    resolveAdminProductEditUrl({
      ...COMPLETE_SAFE_LINK,
      legacyConfigurationProduct: true,
    }).reason,
    "legacy_configuration_product",
  );

  assert.equal(
    resolveAdminProductEditUrl({
      ...COMPLETE_SAFE_LINK,
      hasProductPriceTiers: true,
    }).reason,
    "wholesale_pricing",
  );
});
