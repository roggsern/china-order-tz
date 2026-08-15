/**
 * Prefer storefront display derivative, then original url/path.
 * Single choke-point for PDP/gallery/card/warm URL selection.
 */
import assert from "node:assert/strict";
import { test } from "node:test";
import { preferStorefrontImageSrc } from "./prefer-storefront-image-src";

test("preferStorefrontImageSrc prefers display_url", () => {
  assert.equal(
    preferStorefrontImageSrc({
      display_url: "https://cdn.example.com/display.webp",
      url: "https://cdn.example.com/original.png",
      path: "products/original.png",
    }),
    "https://cdn.example.com/display.webp",
  );
});

test("preferStorefrontImageSrc falls back to url then path", () => {
  assert.equal(
    preferStorefrontImageSrc({
      display_url: null,
      url: "https://cdn.example.com/original.png",
      path: "products/original.png",
    }),
    "https://cdn.example.com/original.png",
  );

  assert.equal(
    preferStorefrontImageSrc({
      display_url: "  ",
      url: null,
      path: "products/original.png",
    }),
    "products/original.png",
  );

  assert.equal(preferStorefrontImageSrc(null), null);
  assert.equal(preferStorefrontImageSrc({}), null);
});
