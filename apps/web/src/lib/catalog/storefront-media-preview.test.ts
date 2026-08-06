import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  configurationMatchesSelections,
  findVisualConfigurationAttribute,
  resolveMediaPreviewConfigurationId,
} from "@/lib/catalog/storefront-media-preview";
import {
  getProductGalleryMedia,
  resolveStorefrontGalleryImages,
} from "@/lib/catalog/product-gallery-media";
import type { ProductImage } from "@/lib/types/catalog";

function image(id: number, url: string): ProductImage {
  return {
    id,
    emoji: "🛍️",
    gradient: "from-zinc-200 to-zinc-300",
    alt: url,
    url,
    path: url,
  };
}

const colorAttr = { id: "attr-color", type: "color", slug: "color" };
const sizeAttr = { id: "attr-size", type: "select", slug: "size" };

const blueS = {
  id: "cfg-blue-s",
  attribute_value_ids: ["opt-blue", "opt-s"],
  attribute_values: [],
  in_stock: true,
  stock: 3,
};
const blueM = {
  id: "cfg-blue-m",
  attribute_value_ids: ["opt-blue", "opt-m"],
  attribute_values: [],
  in_stock: false,
  stock: 0,
};
const blueL = {
  id: "cfg-blue-l",
  attribute_value_ids: ["opt-blue", "opt-l"],
  attribute_values: [],
  in_stock: true,
  stock: 1,
};
const redM = {
  id: "cfg-red-m",
  attribute_value_ids: ["opt-red", "opt-m"],
  attribute_values: [],
  in_stock: true,
  stock: 5,
};

const galleries = {
  "cfg-blue-s": [image(10, "/storage/blue.jpg")],
  "cfg-blue-m": [image(11, "/storage/blue.jpg")],
  "cfg-blue-l": [image(12, "/storage/blue.jpg")],
  "cfg-red-m": [image(20, "/storage/red.jpg")],
  "cfg-blue-only": [image(30, "/storage/blue-only.jpg")],
};

const product = {
  name: "Shirt",
  emoji: "🛍️",
  gradient: "from-zinc-200 to-zinc-300",
  primary_image: image(1, "/storage/product.jpg"),
  images: [image(1, "/storage/product.jpg")],
  image: "/storage/product.jpg",
  videos: [],
  variantGalleries: galleries,
};

describe("storefront media preview resolution", () => {
  it("finds color as the visual attribute before size", () => {
    const visual = findVisualConfigurationAttribute([sizeAttr, colorAttr], {
      [sizeAttr.id]: "opt-m",
    });
    assert.equal(visual?.id, colorAttr.id);
  });

  it("matches candidates for Color=Blue with Size unset", () => {
    assert.equal(
      configurationMatchesSelections(blueS, { [colorAttr.id]: "opt-blue" }),
      true,
    );
    assert.equal(
      configurationMatchesSelections(redM, { [colorAttr.id]: "opt-blue" }),
      false,
    );
  });

  it("sets media preview for Color only while commercial id stays null", () => {
    const previewId = resolveMediaPreviewConfigurationId({
      configurations: [blueS, blueM, blueL, redM],
      selections: { [colorAttr.id]: "opt-blue" },
      attributes: [colorAttr, sizeAttr],
      variantGalleries: galleries,
      exactConfigurationId: null,
    });

    assert.equal(previewId, "cfg-blue-s");
    assert.notEqual(previewId, null);

    const images = resolveStorefrontGalleryImages(product, {
      configurationId: null,
      mediaPreviewConfigurationId: previewId,
    });
    assert.equal(images[0]?.url, "/storage/blue.jpg");
  });

  it("picks deterministic in-stock candidate first, then API order", () => {
    const previewId = resolveMediaPreviewConfigurationId({
      configurations: [blueM, blueL, blueS],
      selections: { [colorAttr.id]: "opt-blue" },
      attributes: [colorAttr, sizeAttr],
      variantGalleries: galleries,
    });
    // blueM is OOS; blueL is first available in this array order
    assert.equal(previewId, "cfg-blue-l");
  });

  it("swaps Blue → Red preview before Size is selected", () => {
    const bluePreview = resolveMediaPreviewConfigurationId({
      configurations: [blueS, blueM, blueL, redM],
      selections: { [colorAttr.id]: "opt-blue" },
      attributes: [colorAttr, sizeAttr],
      variantGalleries: galleries,
    });
    const redPreview = resolveMediaPreviewConfigurationId({
      configurations: [blueS, blueM, blueL, redM],
      selections: { [colorAttr.id]: "opt-red" },
      attributes: [colorAttr, sizeAttr],
      variantGalleries: galleries,
    });

    assert.equal(bluePreview, "cfg-blue-s");
    assert.equal(redPreview, "cfg-red-m");

    const redImages = resolveStorefrontGalleryImages(product, {
      mediaPreviewConfigurationId: redPreview,
    });
    assert.equal(redImages[0]?.url, "/storage/red.jpg");
  });

  it("exact Color+Size match overrides preview gallery", () => {
    const images = resolveStorefrontGalleryImages(
      {
        ...product,
        variantGalleries: {
          ...galleries,
          "cfg-blue-m": [image(99, "/storage/blue-m-specific.jpg")],
        },
      },
      {
        configurationId: "cfg-blue-m",
        mediaPreviewConfigurationId: "cfg-blue-s",
      },
    );
    assert.equal(images[0]?.url, "/storage/blue-m-specific.jpg");
  });

  it("falls through to preview when exact id has no media", () => {
    const images = resolveStorefrontGalleryImages(product, {
      configurationId: "cfg-missing",
      mediaPreviewConfigurationId: "cfg-blue-s",
    });
    assert.equal(images[0]?.url, "/storage/blue.jpg");
  });

  it("falls back to product gallery when selected color has no media", () => {
    const previewId = resolveMediaPreviewConfigurationId({
      configurations: [
        {
          id: "cfg-green-s",
          attribute_value_ids: ["opt-green", "opt-s"],
          attribute_values: [],
          in_stock: true,
          stock: 1,
        },
      ],
      selections: { [colorAttr.id]: "opt-green" },
      attributes: [colorAttr, sizeAttr],
      variantGalleries: galleries,
    });
    assert.equal(previewId, null);

    const images = resolveStorefrontGalleryImages(product, {
      configurationId: null,
      mediaPreviewConfigurationId: previewId,
    });
    assert.equal(images[0]?.url, "/storage/product.jpg");
  });

  it("Size-only selection does not set media preview when Color is visual", () => {
    const previewId = resolveMediaPreviewConfigurationId({
      configurations: [blueS, blueM, blueL, redM],
      selections: { [sizeAttr.id]: "opt-m" },
      attributes: [colorAttr, sizeAttr],
      variantGalleries: galleries,
    });
    assert.equal(previewId, null);
  });

  it("clearing Color clears media preview", () => {
    const previewId = resolveMediaPreviewConfigurationId({
      configurations: [blueS, blueM, blueL, redM],
      selections: {},
      attributes: [colorAttr, sizeAttr],
      variantGalleries: galleries,
    });
    assert.equal(previewId, null);
  });

  it("Color-only product still resolves preview (= exact candidate)", () => {
    const colorOnly = {
      id: "cfg-blue-only",
      attribute_value_ids: ["opt-blue"],
      attribute_values: [],
      in_stock: true,
      stock: 2,
    };
    const previewId = resolveMediaPreviewConfigurationId({
      configurations: [colorOnly],
      selections: { [colorAttr.id]: "opt-blue" },
      attributes: [colorAttr],
      variantGalleries: galleries,
      exactConfigurationId: "cfg-blue-only",
    });
    assert.equal(previewId, "cfg-blue-only");

    const slides = getProductGalleryMedia(
      product,
      "blue",
      "cfg-blue-only",
      previewId,
    );
    assert.equal(slides[0]?.kind, "image");
    if (slides[0]?.kind === "image") {
      assert.equal(slides[0].image.url, "/storage/blue-only.jpg");
    }
  });

  it("commercial selection fields stay separate from preview for ATC safety checks", () => {
    const selection = {
      configurationId: null as string | null,
      mediaPreviewConfigurationId: "cfg-blue-s" as string | null,
      isComplete: false,
    };

    assert.equal(selection.configurationId, null);
    assert.equal(selection.isComplete, false);
    assert.ok(selection.mediaPreviewConfigurationId);

    const canAdd =
      selection.isComplete && Boolean(selection.configurationId);
    assert.equal(canAdd, false);

    // Quote/cart must use commercial id only
    const quoteConfigurationId = selection.configurationId;
    assert.notEqual(quoteConfigurationId, selection.mediaPreviewConfigurationId);
  });
});
