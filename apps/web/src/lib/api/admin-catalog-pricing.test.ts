import assert from "node:assert/strict";
import { test } from "node:test";
import {
  mapAdminApiCatalogProduct,
  productFormDataToCreatePayload,
  type AdminApiProduct,
  type AdminCatalogProductWritePayload,
} from "./admin-catalog";
import type { ProductFormData } from "@/lib/types/catalog";

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

test("mapAdminApiCatalogProduct maps product-level volume tiers and ignores configuration rows", () => {
  const mapped = mapAdminApiCatalogProduct({
    id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
    name: "Volume Phone",
    slug: "volume-phone",
    price: "10000.00",
    status: "draft",
    visibility: "public",
    price_tiers: [
      {
        min_quantity: 50,
        unit_price: "6000.00",
        tier_type: "fixed_unit",
        configuration_id: null,
      },
      {
        min_quantity: 10,
        unit_price: "8000.00",
        tier_type: "fixed_unit",
        configuration_id: null,
      },
      {
        min_quantity: 5,
        unit_price: "7000.00",
        tier_type: "fixed_unit",
        configuration_id: "cfg-red",
      },
    ],
    variants: [
      {
        id: "cfg-red",
        sku: "RED",
        price_tiers: [{ min_quantity: 5, unit_price: "7000.00", tier_type: "fixed_unit" }],
      },
    ],
  } satisfies AdminApiProduct);

  assert.equal(mapped.priceTiers.length, 2);
  assert.equal(mapped.priceTiers[0]?.minQuantity, 10);
  assert.equal(mapped.priceTiers[1]?.minQuantity, 50);
  assert.equal(mapped.hasConfigurationPriceTiers, true);
});

function legacyForm(overrides: Partial<ProductFormData> = {}): ProductFormData {
  return {
    name: "Lip Gloss",
    slug: "lip-gloss",
    shortDescription: "",
    description: "Lip gloss",
    fullDescription: "",
    price: 10000,
    oldPrice: 0,
    discountPercent: 0,
    rating: 0,
    reviews: 0,
    badge: "",
    gradient: "from-zinc-500 to-zinc-700",
    emoji: "📦",
    type: "china",
    origin: "china",
    categoryId: "cat-1",
    parentCategoryId: "",
    brandId: "",
    brandSlug: "",
    brand: "",
    categorySlug: "",
    subcategorySlug: "",
    stock: 10,
    sku: "SKU-1",
    skuOverride: false,
    weightKg: null,
    airCost: 18000,
    seaCost: 9500,
    airAvailable: true,
    seaAvailable: true,
    airNotes: "",
    seaNotes: "",
    airDeliveryDays: "",
    seaDeliveryDays: "",
    features: "",
    featured: false,
    bestSeller: false,
    trending: false,
    newArrival: false,
    status: "active",
    isDemo: false,
    minimumOrderQuantity: null,
    orderIncrement: null,
    wholesaleEnabled: true,
    priceTiers: [
      { minQuantity: 10, tierType: "fixed_unit", unitPrice: 8000, discountPercent: null },
    ],
    images: [],
    thumbnailImageId: null,
    variants: {},
    configurations: [],
    ...overrides,
  };
}

test("legacy simple product payload still sends product-level price_tiers", () => {
  const payload = productFormDataToCreatePayload(legacyForm());
  assert.deepEqual(payload.price_tiers, [
    { min_quantity: 10, tier_type: "fixed_unit", unit_price: 8000, discount_percent: null },
  ]);
});

test("legacy configurable product payload omits product-level price_tiers so it cannot wipe them", () => {
  const payload = productFormDataToCreatePayload(
    legacyForm({
      configurations: [
        {
          attributeValueIds: ["red"],
          label: "Red",
          sku: "RED",
          stock: 4,
          price: 10000,
          barcode: "",
          priceTiers: [
            { minQuantity: 5, tierType: "fixed_unit", unitPrice: 7000, discountPercent: null },
          ],
        },
      ],
    }),
  );

  assert.equal("price_tiers" in payload, false);
  assert.deepEqual(payload.configurations?.[0]?.price_tiers, [
    { min_quantity: 5, tier_type: "fixed_unit", unit_price: 7000, discount_percent: null },
  ]);
});
