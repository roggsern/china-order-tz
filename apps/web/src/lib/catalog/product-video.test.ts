import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  isSupportedProductVideoUrl,
  resolveProductVideoEmbedUrl,
  resolveProductVideoThumbnail,
  vimeoVideoId,
  youtubeVideoId,
} from "./product-video";

describe("product-video", () => {
  it("detects supported YouTube and Vimeo URLs", () => {
    assert.equal(
      isSupportedProductVideoUrl("https://www.youtube.com/watch?v=dQw4w9WgXcQ"),
      true,
    );
    assert.equal(isSupportedProductVideoUrl("https://vimeo.com/123456789"), true);
    assert.equal(isSupportedProductVideoUrl("https://example.com/video.mp4"), false);
  });

  it("builds embed URLs for supported providers", () => {
    assert.equal(
      resolveProductVideoEmbedUrl("https://youtu.be/dQw4w9WgXcQ"),
      "https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0&modestbranding=1",
    );
    assert.equal(
      resolveProductVideoEmbedUrl("https://vimeo.com/123456789"),
      "https://player.vimeo.com/video/123456789",
    );
    assert.equal(resolveProductVideoEmbedUrl("https://broken.example/watch"), null);
  });

  it("uses API thumbnail or derives YouTube thumbnail", () => {
    assert.equal(
      resolveProductVideoThumbnail({
        url: "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
        thumbnail_url: null,
      }),
      "https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg",
    );
    assert.equal(
      resolveProductVideoThumbnail({
        url: "https://vimeo.com/123456789",
        thumbnail_url: "https://cdn.example.com/vimeo-thumb.jpg",
      }),
      "https://cdn.example.com/vimeo-thumb.jpg",
    );
  });

  it("extracts provider ids", () => {
    assert.equal(youtubeVideoId("https://www.youtube.com/embed/abc12345"), "abc12345");
    assert.equal(vimeoVideoId("https://vimeo.com/987654321"), "987654321");
  });
});
