import assert from "node:assert/strict";
import { test } from "node:test";
import type { ApiCatalogProductDetail } from "@/lib/api/products";
import { mapApiProductDetailToCatalogProduct } from "./map-api-product";

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

test("detail mapping prefers display_url for gallery and variant galleries", () => {
  const mapped = mapApiProductDetailToCatalogProduct({
    ...BASE_PRODUCT,
    primary_image: {
      id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
      path: "products/master.jpg",
      url: "/storage/products/master.jpg",
      original_url: "/storage/products/master.jpg",
      display_url: "/storage/products/storefront/master.webp",
      alt_text: "Master",
    },
    images: [
      {
        id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
        path: "products/master.jpg",
        url: "/storage/products/master.jpg",
        display_url: "/storage/products/storefront/master.webp",
        alt_text: "Master",
      },
    ],
    variants: [
      {
        id: "019f7a6e-4d46-7376-aca4-bbbbbbbbbbbb",
        sku: "SKU-1",
        name: "Blue",
        price: "1000",
        compare_at_price: null,
        weight: null,
        primary_image: {
          id: "019f7a6e-4d46-7376-aca4-cccccccccccc",
          path: "products/variant.jpg",
          url: "/storage/products/variant.jpg",
          display_url: "/storage/products/storefront/variant.webp",
          alt_text: "Variant",
        },
        images: [
          {
            id: "019f7a6e-4d46-7376-aca4-cccccccccccc",
            path: "products/variant.jpg",
            url: "/storage/products/variant.jpg",
            display_url: "/storage/products/storefront/variant.webp",
            alt_text: "Variant",
          },
        ],
      },
    ],
  });

  assert.equal(mapped.primary_image?.url, "/storage/products/storefront/master.webp");
  assert.equal(mapped.images[0]?.url, "/storage/products/storefront/master.webp");
  assert.equal(
    mapped.variantGalleries?.["019f7a6e-4d46-7376-aca4-bbbbbbbbbbbb"]?.[0]?.url,
    "/storage/products/storefront/variant.webp",
  );
});

test("detail mapping falls back to url when display_url absent", () => {
  const mapped = mapApiProductDetailToCatalogProduct({
    ...BASE_PRODUCT,
    primary_image: {
      id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
      path: "products/master.jpg",
      url: "/storage/products/master.jpg",
      display_url: null,
      alt_text: "Master",
    },
    images: [
      {
        id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
        path: "products/master.jpg",
        url: "/storage/products/master.jpg",
        alt_text: "Master",
      },
    ],
  });

  assert.equal(mapped.primary_image?.url, "/storage/products/master.jpg");
  assert.equal(mapped.images[0]?.url, "/storage/products/master.jpg");
});
