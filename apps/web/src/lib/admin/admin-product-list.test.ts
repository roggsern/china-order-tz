import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  adminProductThumbnailUrl,
  formatAdminChannelBadge,
  formatAdminPriceRange,
  formatAdminStockSummary,
} from "@/lib/admin/admin-product-list";
import { mapAdminApiCatalogProduct, type AdminApiProduct } from "@/lib/api/admin-catalog";

describe("admin-product-list", () => {
  it("formats channel badges for China and TZ", () => {
    assert.equal(formatAdminChannelBadge("CHINA_IMPORT")?.label, "China Import");
    assert.equal(formatAdminChannelBadge("TZ_LOCAL")?.label, "Buy From TZ");
    assert.equal(formatAdminChannelBadge(null), null);
  });

  it("formats price ranges and stock summaries", () => {
    assert.equal(
      formatAdminPriceRange({ min: 15000, max: 28000, currency: "TZS" }).includes("–"),
      true,
    );
    assert.equal(formatAdminPriceRange({ min: 10000, max: 10000, currency: "TZS" }).includes("–"), false);
    assert.match(
      formatAdminStockSummary(
        {
          path: "variant",
          totalAvailable: 10,
          variantsInStock: 1,
          variantsOutOfStock: 1,
        },
        2,
      ),
      /10 avail/,
    );
    assert.match(
      formatAdminStockSummary({
        path: "simple",
        totalAvailable: 4,
        variantsInStock: 0,
        variantsOutOfStock: 0,
      }),
      /4 available/,
    );
  });

  it("resolves thumbnail urls with storage fallback", () => {
    assert.equal(
      adminProductThumbnailUrl({
        id: "1",
        path: null,
        url: "https://cdn.example/a.jpg",
        altText: null,
      }),
      "https://cdn.example/a.jpg",
    );
    assert.equal(
      adminProductThumbnailUrl({
        id: "2",
        path: "products/a.jpg",
        url: null,
        altText: null,
      }),
      "/storage/products/a.jpg",
    );
  });

  it("maps listing summary fields from API product payloads", () => {
    const product: AdminApiProduct = {
      id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
      name: "Blouse",
      slug: "blouse",
      price: 20000,
      commerce_channel: {
        id: "ch-1",
        name: "Buy From Tanzania",
        code: "TZ_LOCAL",
        admin_label: "Buy From TZ",
      },
      store: { id: "st-1", name: "Dar Central", code: "DSM-01" },
      image: {
        id: "img-1",
        url: "/storage/products/blouse.jpg",
        path: "products/blouse.jpg",
        alt_text: "Blouse",
      },
      variants_count: 4,
      price_range: { min: "15000.00", max: "28000.00", currency: "TZS" },
      stock_summary: {
        path: "variant",
        total_available: 12,
        variants_in_stock: 3,
        variants_out_of_stock: 1,
      },
    };

    const mapped = mapAdminApiCatalogProduct(product);
    assert.equal(mapped.commerceChannelCode, "TZ_LOCAL");
    assert.equal(mapped.storeName, "Dar Central");
    assert.equal(mapped.image?.url, "/storage/products/blouse.jpg");
    assert.equal(mapped.variantsCount, 4);
    assert.equal(mapped.priceRange?.min, 15000);
    assert.equal(mapped.priceRange?.max, 28000);
    assert.equal(mapped.stockSummary?.totalAvailable, 12);
  });
});
