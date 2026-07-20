import assert from "node:assert/strict";
import { describe, it } from "node:test";
import type { HomepageHeroSlide } from "./types";

/** Pure navigation helpers mirrored by HeroCarousel (kept testable without DOM). */
export function nextSlideIndex(current: number, count: number): number {
  if (count <= 0) return 0;
  return (current + 1) % count;
}

export function prevSlideIndex(current: number, count: number): number {
  if (count <= 0) return 0;
  return (current - 1 + count) % count;
}

export function resolveActiveSlide(
  slides: HomepageHeroSlide[],
  index: number,
): HomepageHeroSlide | null {
  if (slides.length === 0) return null;
  return slides[((index % slides.length) + slides.length) % slides.length] ?? null;
}

describe("hero carousel navigation", () => {
  const slides = [
    { id: "a" },
    { id: "b" },
    { id: "c" },
  ] as HomepageHeroSlide[];

  it("advances and wraps forward", () => {
    assert.equal(nextSlideIndex(0, 3), 1);
    assert.equal(nextSlideIndex(2, 3), 0);
  });

  it("moves and wraps backward", () => {
    assert.equal(prevSlideIndex(0, 3), 2);
    assert.equal(prevSlideIndex(1, 3), 0);
  });

  it("resolves the active slide safely", () => {
    assert.equal(resolveActiveSlide(slides, 0)?.id, "a");
    assert.equal(resolveActiveSlide(slides, 5)?.id, "c");
    assert.equal(resolveActiveSlide([], 0), null);
  });
});
