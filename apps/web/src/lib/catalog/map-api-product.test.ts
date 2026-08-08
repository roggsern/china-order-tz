import assert from "node:assert/strict";
import { test } from "node:test";
import type { ApiCatalogProductDetail } from "@/lib/api/products";
import {
  mapApiProductCardToCatalogProduct,
  mapApiProductDetailToCatalogProduct,
  resolveApiProductStock,
} from "./map-api-product";

const BASE_PRODUCT: ApiCatalogProductDetail = {
  id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
  slug: "catalog-phone",
  name: "Catalog Phone",
  short_description: "Phone",
  description: "Phone description",
  price: "1000",
  compare_at_price: null,
  weight: null,
  dimensions: null,
  is_featured: false,
  primary_image: null,
  category: null,
  brand: null,
  average_rating: 0,
  review_count: 0,
  shipping_prices: { air: null, sea: null },
  images: [],
  variants: [],
};

test("Case 1: API returns stock quantity 10 => frontend product stock = 10", () => {
  assert.equal(
    resolveApiProductStock({
      stock: 10,
    }),
    10,
  );

  const mapped = mapApiProductDetailToCatalogProduct({
    ...BASE_PRODUCT,
    stock: 10,
  });

  assert.equal(mapped.stock, 10);
});

test("Case 2: API returns stock quantity 0 => frontend product stock = 0", () => {
  assert.equal(
    resolveApiProductStock({
      stock: 0,
    }),
    0,
  );

  const mapped = mapApiProductDetailToCatalogProduct({
    ...BASE_PRODUCT,
    stock: 0,
  });

  assert.equal(mapped.stock, 0);
  assert.equal(mapApiProductCardToCatalogProduct({ ...BASE_PRODUCT, stock: 0 }).stock, 0);
});

test("Case 3: API does not return stock => frontend fallback = 0", () => {
  assert.equal(resolveApiProductStock({}), 0);
  assert.equal(mapApiProductDetailToCatalogProduct(BASE_PRODUCT).stock, 0);
  assert.equal(mapApiProductCardToCatalogProduct(BASE_PRODUCT).stock, 0);
});

test("china import card uses in_stock when numeric stock is absent", () => {
  assert.equal(
    resolveApiProductStock({
      in_stock: true,
    }),
    1,
  );

  const mapped = mapApiProductCardToCatalogProduct({
    ...BASE_PRODUCT,
    commerce_channel_code: "CHINA_IMPORT",
    in_stock: true,
  });

  assert.equal(mapped.stock, 1);
  assert.equal(mapped.origin, "china");
});

test("china import variant in_stock aggregates when numeric stock is absent", () => {
  const mapped = mapApiProductCardToCatalogProduct({
    ...BASE_PRODUCT,
    commerce_channel_code: "CHINA_IMPORT",
    variants: [
      {
        id: "019f7a6e-bbbb-7376-aca4-aed79f33519b",
        sku: "SKU-CN-1",
        name: "128GB",
        price: "1000",
        compare_at_price: null,
        weight: null,
        in_stock: true,
      },
      {
        id: "019f7a6e-cccc-7376-aca4-aed79f33519b",
        sku: "SKU-CN-2",
        name: "256GB",
        price: "1000",
        compare_at_price: null,
        weight: null,
        in_stock: false,
      },
    ],
  });

  assert.equal(mapped.stock, 1);
});

test("TZ_LOCAL regression: numeric stock mapping unchanged", () => {
  const mapped = mapApiProductCardToCatalogProduct({
    ...BASE_PRODUCT,
    commerce_channel_code: "TZ_LOCAL",
    stock: 12,
    in_stock: true,
  });

  assert.equal(mapped.stock, 12);
  assert.equal(mapped.origin, "tz");
});

test("add to cart availability uses mapped stock for china import cards", () => {
  const mapped = mapApiProductCardToCatalogProduct({
    ...BASE_PRODUCT,
    commerce_channel_code: "CHINA_IMPORT",
    variants: [
      {
        id: "019f7a6e-bbbb-7376-aca4-aed79f33519b",
        sku: "SKU-CN-1",
        name: "128GB",
        price: "1000",
        compare_at_price: null,
        weight: null,
        stock: 4,
        in_stock: true,
      },
    ],
  });

  assert.ok(mapped.stock > 0);
});

test("listing card stock is mapped from API card payload", () => {
  const mapped = mapApiProductCardToCatalogProduct({
    ...BASE_PRODUCT,
    stock: 15,
    in_stock: true,
  });

  assert.equal(mapped.stock, 15);
});

test("listing card variant stock aggregates when product stock is absent", () => {
  const mapped = mapApiProductCardToCatalogProduct({
    ...BASE_PRODUCT,
    variants: [
      {
        id: "019f7a6e-bbbb-7376-aca4-aed79f33519b",
        sku: "SKU-2",
        name: "Blue",
        price: "1000",
        compare_at_price: null,
        weight: null,
        stock: 4,
        in_stock: true,
      },
      {
        id: "019f7a6e-cccc-7376-aca4-aed79f33519b",
        sku: "SKU-3",
        name: "Red",
        price: "1000",
        compare_at_price: null,
        weight: null,
        stock: 3,
        in_stock: true,
      },
    ],
  });

  assert.equal(mapped.stock, 7);
});

test("variant stock is mapped when product-level stock is absent", () => {
  const mapped = mapApiProductDetailToCatalogProduct({
    ...BASE_PRODUCT,
    variants: [
      {
        id: "019f7a6e-aaaa-7376-aca4-aed79f33519b",
        sku: "SKU-1",
        name: "Default",
        price: "1000",
        compare_at_price: null,
        weight: null,
        stock: 7,
      },
    ],
  });

  assert.equal(mapped.stock, 7);
});

test("maps detail videos separately without merging into images", () => {
  const mapped = mapApiProductDetailToCatalogProduct({
    ...BASE_PRODUCT,
    images: [
      {
        id: "img-1",
        path: "demo-products/phone.jpg",
        url: "https://cdn.example.com/phone.jpg",
        alt_text: "Phone",
      },
    ],
    videos: [
      {
        id: "video-1",
        url: "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
        thumbnail_url: "https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg",
        title: "Demo video",
        alt_text: "Watch the phone",
        sort_order: 1,
      },
    ],
  });

  assert.equal(mapped.images.length, 1);
  assert.equal(mapped.videos?.length, 1);
  assert.equal(mapped.videos?.[0]?.id, "video-1");
  assert.equal(mapped.videos?.[0]?.title, "Demo video");
  assert.equal(mapped.images.some((image) => image.url?.includes("youtube")), false);
});

test("product without videos keeps undefined videos field", () => {
  const mapped = mapApiProductDetailToCatalogProduct({
    ...BASE_PRODUCT,
    images: [
      {
        id: "img-1",
        path: "demo-products/phone.jpg",
        url: "https://cdn.example.com/phone.jpg",
        alt_text: "Phone",
      },
    ],
  });

  assert.equal(mapped.images.length, 1);
  assert.equal(mapped.videos, undefined);
});

test("detail mapping preserves purchasability availability fields", () => {
  const mapped = mapApiProductDetailToCatalogProduct({
    ...BASE_PRODUCT,
    is_purchasable: false,
    availability_status: "unavailable",
    unavailability_reason: "missing_inventory_policy",
  });

  assert.equal(mapped.isPurchasable, false);
  assert.equal(mapped.availabilityStatus, "unavailable");
  assert.equal(mapped.unavailabilityReason, "missing_inventory_policy");
});

test("detail mapping defaults purchasable product to available fields", () => {
  const mapped = mapApiProductDetailToCatalogProduct({
    ...BASE_PRODUCT,
    is_purchasable: true,
    availability_status: "available",
    stock: 4,
  });

  assert.equal(mapped.isPurchasable, true);
  assert.equal(mapped.availabilityStatus, "available");
  assert.equal(mapped.stock, 4);
});

test("invalid video URLs are still mapped but filtered at gallery layer", () => {
  const mapped = mapApiProductDetailToCatalogProduct({
    ...BASE_PRODUCT,
    videos: [
      {
        id: "video-bad",
        url: "https://example.com/not-supported",
        sort_order: 0,
      },
    ],
  });

  assert.equal(mapped.videos?.length, 1);
});

test("prefers API specifications over dimensions/weight fallback", () => {
  const mapped = mapApiProductDetailToCatalogProduct({
    ...BASE_PRODUCT,
    dimensions: "10 x 5 x 1 cm",
    weight: "1.2",
    specifications: [
      { label: "RAM", value: "12GB" },
      { label: "Battery Capacity", value: "5000 mAh" },
    ],
  });

  assert.deepEqual(mapped.specifications, [
    { label: "RAM", value: "12GB" },
    { label: "Battery Capacity", value: "5000 mAh" },
  ]);
});

test("falls back to dimensions and weight when API specifications are empty", () => {
  const mapped = mapApiProductDetailToCatalogProduct({
    ...BASE_PRODUCT,
    dimensions: "10 x 5 x 1 cm",
    weight: "1.25",
    specifications: [],
  });

  assert.deepEqual(mapped.specifications, [
    { label: "Dimensions", value: "10 x 5 x 1 cm" },
    { label: "Weight", value: "1.25 kg" },
  ]);
});
