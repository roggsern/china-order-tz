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
