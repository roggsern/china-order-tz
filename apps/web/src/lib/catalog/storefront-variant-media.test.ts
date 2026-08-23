import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  getProductGalleryMedia,
  resolveStorefrontGalleryImages,
} from "@/lib/catalog/product-gallery-media";
import {
  resolveCartLineDisplayImage,
  resolveVariantCartImage,
} from "@/lib/catalog/storefront-variant-media";
import type { Product, ProductImage } from "@/lib/types/catalog";

function image(id: number, url: string, alt = "img"): ProductImage {
  return {
    id,
    emoji: "🛍️",
    gradient: "from-zinc-200 to-zinc-300",
    alt,
    url,
    path: url,
  };
}

const baseProduct: Pick<
  Product,
  | "name"
  | "emoji"
  | "gradient"
  | "primary_image"
  | "images"
  | "image"
  | "videos"
  | "variantGalleries"
> = {
  name: "Blouse",
  emoji: "🛍️",
  gradient: "from-zinc-200 to-zinc-300",
  primary_image: image(1, "/storage/product-primary.jpg", "product"),
  images: [
    image(1, "/storage/product-primary.jpg", "product"),
    image(2, "/storage/product-black.jpg", "black blouse"),
  ],
  image: "/storage/product-primary.jpg",
  videos: [],
  variantGalleries: {
    "variant-black": [image(10, "/storage/variant-black.jpg", "black variant")],
  },
};

describe("storefront variant gallery selection", () => {
  it("updates gallery images when a variant configuration is selected", () => {
    const images = resolveStorefrontGalleryImages(baseProduct, {
      configurationId: "variant-black",
    });
    assert.equal(images.length, 1);
    assert.equal(images[0]?.url, "/storage/variant-black.jpg");

    const slides = getProductGalleryMedia(baseProduct, null, "variant-black");
    assert.equal(slides[0]?.kind, "image");
    if (slides[0]?.kind === "image") {
      assert.equal(slides[0].image.url, "/storage/variant-black.jpg");
    }
  });

  it("uses media preview gallery when commercial configuration is still null", () => {
    const images = resolveStorefrontGalleryImages(baseProduct, {
      configurationId: null,
      mediaPreviewConfigurationId: "variant-black",
    });
    assert.equal(images[0]?.url, "/storage/variant-black.jpg");

    const slides = getProductGalleryMedia(baseProduct, null, null, "variant-black");
    assert.equal(slides[0]?.kind, "image");
    if (slides[0]?.kind === "image") {
      assert.equal(slides[0].image.url, "/storage/variant-black.jpg");
    }
  });

  it("falls back to product gallery when variant has no dedicated media map", () => {
    const images = resolveStorefrontGalleryImages(baseProduct, {
      configurationId: "variant-missing",
      selectedColorSlug: "black",
    });
    assert.ok(images.some((entry) => entry.url?.includes("black")));
  });
});

describe("cart and checkout variant image display", () => {
  it("prefers variant primary image for cart lines", () => {
    const resolved = resolveCartLineDisplayImage(
      {
        product: {
          name: "Blouse",
          primary_image: {
            id: "p1",
            url: "/storage/product.jpg",
            alt_text: "product",
          },
          images: [{ id: "p1", url: "/storage/product.jpg", is_primary: true }],
        },
        variant: {
          primary_image: {
            id: "v1",
            url: "/storage/variant.jpg",
            alt_text: "variant",
          },
          images: [{ id: "v1", url: "/storage/variant.jpg" }],
        },
      },
      "line-1",
    );

    assert.equal(resolved.url, "/storage/variant.jpg");
    assert.equal(resolved.alt, "variant");
  });

  it("falls back to product image when variant media is absent", () => {
    const resolved = resolveCartLineDisplayImage(
      {
        product: {
          name: "Blouse",
          images: [{ id: "p1", url: "/storage/product.jpg", is_primary: true, alt: "product" }],
        },
        variant: { images: [] },
      },
      "line-2",
    );

    assert.equal(resolved.url, "/storage/product.jpg");
  });

  it("keeps distinct selected-variant images for two lines of the same product", () => {
    const product = {
      name: "STRETCH PENCIL SKIRTS",
      primary_image: {
        id: "product-main",
        url: "/storage/skirts/product-main.jpg",
        alt_text: "product",
      },
    };

    const black = resolveCartLineDisplayImage(
      {
        product,
        variant: {
          primary_image: {
            id: "black-s",
            url: "/storage/skirts/black-s.jpg",
            alt_text: "Black S",
          },
        },
      },
      "line-black-s",
    );
    const red = resolveCartLineDisplayImage(
      {
        product,
        variant: {
          primary_image: {
            id: "red-xxl",
            url: "/storage/skirts/red-xxl.jpg",
            alt_text: "Red XXL",
          },
        },
      },
      "line-red-xxl",
    );

    assert.equal(black.url, "/storage/skirts/black-s.jpg");
    assert.equal(red.url, "/storage/skirts/red-xxl.jpg");
    assert.notEqual(black.url, red.url);
  });

  it("uses variant gallery for local cart snapshots", () => {
    const image = resolveVariantCartImage(baseProduct as Product, "variant-black");
    assert.equal(image.url, "/storage/variant-black.jpg");
  });
});
