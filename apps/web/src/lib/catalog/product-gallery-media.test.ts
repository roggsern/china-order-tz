import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { getProductGalleryMedia } from "./product-gallery-media";
import type { Product } from "@/lib/types/catalog";

const BASE_PRODUCT: Pick<
  Product,
  "primary_image" | "images" | "image" | "name" | "emoji" | "gradient" | "videos"
> = {
  name: "Gallery Phone",
  emoji: "📱",
  gradient: "from-zinc-800 to-zinc-900",
  images: [
    {
      id: 1,
      emoji: "📱",
      gradient: "from-zinc-800 to-zinc-900",
      alt: "Front",
      url: "https://cdn.example.com/front.jpg",
    },
    {
      id: 2,
      emoji: "📱",
      gradient: "from-zinc-800 to-zinc-900",
      alt: "Back",
      url: "https://cdn.example.com/back.jpg",
    },
  ],
};

describe("getProductGalleryMedia", () => {
  it("returns image-only slides when videos are absent", () => {
    const media = getProductGalleryMedia(BASE_PRODUCT);

    assert.equal(media.length, 2);
    assert.equal(media.every((slide) => slide.kind === "image"), true);
  });

  it("appends supported videos after images using sort_order", () => {
    const media = getProductGalleryMedia({
      ...BASE_PRODUCT,
      videos: [
        {
          id: "video-b",
          url: "https://vimeo.com/222222222",
          sort_order: 2,
        },
        {
          id: "video-a",
          url: "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
          sort_order: 1,
        },
      ],
    });

    assert.equal(media.length, 4);
    assert.equal(media[0]?.kind, "image");
    assert.equal(media[1]?.kind, "image");
    assert.equal(media[2]?.kind, "video");
    assert.equal(media[2]?.kind === "video" ? media[2].video.id : null, "video-a");
    assert.equal(media[3]?.kind, "video");
    assert.equal(media[3]?.kind === "video" ? media[3].video.id : null, "video-b");
  });

  it("ignores unsupported video URLs", () => {
    const media = getProductGalleryMedia({
      ...BASE_PRODUCT,
      videos: [
        {
          id: "bad-video",
          url: "https://example.com/not-a-video.mp4",
          sort_order: 0,
        },
      ],
    });

    assert.equal(media.length, 2);
    assert.equal(media.every((slide) => slide.kind === "image"), true);
  });
});
