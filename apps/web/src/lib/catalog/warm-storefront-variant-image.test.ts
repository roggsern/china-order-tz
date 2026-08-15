import assert from "node:assert/strict";
import { test } from "node:test";
import {
  hasInFlightWarmedStorefrontVariantImage,
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

test("warmStorefrontVariantPrimaryImage warms once and retains Image until load", async () => {
  resetWarmedStorefrontVariantImages();

  const urls: string[] = [];
  const OriginalImage = globalThis.Image;
  const previousWindow = globalThis.window;
  const instances: FakeImage[] = [];

  class FakeImage {
    onload: ((this: FakeImage, ev?: unknown) => void) | null = null;
    onerror: ((this: FakeImage, ev?: unknown) => void) | null = null;
    decoding = "";
    fetchPriority = "";
    private _src = "";

    constructor() {
      instances.push(this);
    }

    set src(value: string) {
      this._src = value;
      urls.push(value);
    }

    get src() {
      return this._src;
    }

    decode() {
      return Promise.resolve();
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
    const primary = "https://cdn.example.com/warm.jpg";
    const other = "https://cdn.example.com/other.jpg";

    warmStorefrontVariantPrimaryImage(primary);
    warmStorefrontVariantPrimaryImage(primary);
    warmStorefrontVariantPrimaryImage(other);

    assert.deepEqual(urls, [primary, other]);
    assert.equal(hasWarmedStorefrontVariantImage(primary), true);
    assert.equal(hasInFlightWarmedStorefrontVariantImage(primary), true);
    assert.equal(instances[0]?.fetchPriority, "low");
    assert.equal(instances[0]?.decoding, "async");

    instances[0]?.onload?.call(instances[0]);
    await Promise.resolve();
    await Promise.resolve();

    assert.equal(hasInFlightWarmedStorefrontVariantImage(primary), false);
    assert.equal(hasWarmedStorefrontVariantImage(primary), true);
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

test("warm and display URLs stay byte-identical via resolveVariantGalleryPrimaryUrl", () => {
  const galleries = {
    "cfg-blue": [
      {
        id: 1,
        url: "https://cdn.example.com/blue-primary.jpg",
        alt: "Blue",
      },
    ],
  };

  const warmUrl = resolveVariantGalleryPrimaryUrl("cfg-blue", galleries);
  const displayRaw = galleries["cfg-blue"][0]?.url;
  assert.equal(warmUrl, displayRaw);
});
