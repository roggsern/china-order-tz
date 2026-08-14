import assert from "node:assert/strict";
import { test } from "node:test";
import {
  hasWarmedStorefrontVariantImage,
  resetWarmedStorefrontVariantImages,
  resolveVariantGalleryPrimaryUrl,
  warmStorefrontVariantPrimaryImage,
} from "./warm-storefront-variant-image";

test("resolveVariantGalleryPrimaryUrl returns only the primary slide", () => {
  const url = resolveVariantGalleryPrimaryUrl("cfg-1", {
    "cfg-1": [
      { id: 1, url: "https://cdn.example.com/primary.jpg", alt: "Primary" },
      { id: 2, url: "https://cdn.example.com/second.jpg", alt: "Second" },
    ],
  });

  assert.equal(url, "https://cdn.example.com/primary.jpg");
});

test("resolveVariantGalleryPrimaryUrl is safe for missing urls and ids", () => {
  assert.equal(resolveVariantGalleryPrimaryUrl(null, { "cfg-1": [] }), null);
  assert.equal(resolveVariantGalleryPrimaryUrl("cfg-missing", {}), null);
  assert.equal(resolveVariantGalleryPrimaryUrl("cfg-1", null), null);
});

test("warmStorefrontVariantPrimaryImage is SSR-safe and dedupes", () => {
  resetWarmedStorefrontVariantImages();

  // Node test environment has no window.Image — warming must no-op safely.
  const hadWindow = typeof globalThis.window !== "undefined";
  warmStorefrontVariantPrimaryImage("https://cdn.example.com/a.jpg");
  warmStorefrontVariantPrimaryImage("https://cdn.example.com/a.jpg");
  warmStorefrontVariantPrimaryImage(null);
  warmStorefrontVariantPrimaryImage("");

  if (!hadWindow) {
    assert.equal(hasWarmedStorefrontVariantImage("https://cdn.example.com/a.jpg"), false);
  }
});

test("warmStorefrontVariantPrimaryImage warms once when Image is available", () => {
  resetWarmedStorefrontVariantImages();

  const urls: string[] = [];
  const OriginalImage = globalThis.Image;
  const previousWindow = globalThis.window;

  class FakeImage {
    set src(value: string) {
      urls.push(value);
    }
  }

  // @ts-expect-error test stub
  globalThis.window = {
    ...(previousWindow ?? {}),
    Image: FakeImage,
  };
  // @ts-expect-error test stub
  globalThis.Image = FakeImage;

  try {
    warmStorefrontVariantPrimaryImage("https://cdn.example.com/warm.jpg");
    warmStorefrontVariantPrimaryImage("https://cdn.example.com/warm.jpg");
    warmStorefrontVariantPrimaryImage("https://cdn.example.com/other.jpg");

    assert.deepEqual(urls, [
      "https://cdn.example.com/warm.jpg",
      "https://cdn.example.com/other.jpg",
    ]);
    assert.equal(hasWarmedStorefrontVariantImage("https://cdn.example.com/warm.jpg"), true);
  } finally {
    globalThis.Image = OriginalImage;
    if (previousWindow === undefined) {
      // @ts-expect-error cleanup
      delete globalThis.window;
    } else {
      globalThis.window = previousWindow;
    }
    resetWarmedStorefrontVariantImages();
  }
});
