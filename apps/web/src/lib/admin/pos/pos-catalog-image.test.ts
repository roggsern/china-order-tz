import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, it } from "node:test";
import {
  posCatalogItemRowKey,
  resolvePosCatalogItemImageSrc,
} from "@/lib/admin/pos/pos-catalog-image";
import { PRODUCT_PLACEHOLDER_IMAGE } from "@/lib/catalog/product-images";

const root = join(process.cwd(), "src");

describe("pos catalog image helpers", () => {
  it("resolves a catalog image URL when primary_image is present", () => {
    const src = resolvePosCatalogItemImageSrc({
      primary_image: {
        id: "media-1",
        url: "https://api.example.com/storage/demo-products/wig.jpg",
        alt_text: "Wig",
      },
    });

    assert.equal(src, "https://api.example.com/storage/demo-products/wig.jpg");
    assert.notEqual(src, PRODUCT_PLACEHOLDER_IMAGE);
  });

  it("falls back to the shared placeholder when primary_image is missing", () => {
    assert.equal(resolvePosCatalogItemImageSrc({}), PRODUCT_PLACEHOLDER_IMAGE);
    assert.equal(resolvePosCatalogItemImageSrc({ primary_image: null }), PRODUCT_PLACEHOLDER_IMAGE);
  });

  it("builds stable row keys for simple and variant catalog rows", () => {
    assert.equal(
      posCatalogItemRowKey({
        product_id: "prod-1",
        product_variant_id: "var-1",
      } as never),
      "prod-1:var-1",
    );
    assert.equal(
      posCatalogItemRowKey({
        product_id: "prod-2",
        product_variant_id: null,
      } as never),
      "prod-2:simple",
    );
  });
});

describe("PosCashierPanel catalog thumbnails", () => {
  it("renders ProductImageDisplay for search results and cart lines", () => {
    const source = readFileSync(
      join(root, "components/admin/pos/PosCashierPanel.tsx"),
      "utf8",
    );

    assert.match(source, /ProductImageDisplay/);
    assert.match(source, /src=\{resolvePosCatalogItemImageSrc\(item\)\}/);
    assert.match(source, /src=\{resolvePosCatalogItemImageSrc\(line\)\}/);
    assert.match(source, /posCatalogItemRowKey/);
  });
});
