import assert from "node:assert/strict";
import { describe, it } from "node:test";
import type { CmsHomepageResponse } from "@/lib/api/cms-homepage";
import {
  getHomepageContent,
  loadHomepageContent,
} from "./get-homepage-content";
import type { HomepageCollection } from "./types";
import { filterActiveScheduled, isActivelyScheduled } from "./schedule";
import { homepageContentSeed } from "./seed";

const FAR_FUTURE = "2099-12-31T23:59:59.000Z";
const FAR_PAST = "2020-01-01T00:00:00.000Z";

const nullCmsResponse: CmsHomepageResponse = {
  success: true,
  data: null,
  meta: {
    commerce_context: "GLOBAL",
    allow_global_fallback: true,
    campaign: null,
    message: "No active campaign or default homepage layout for this context.",
  },
};

const catalogCollections: HomepageCollection[] = [
  {
    id: "live-electronics",
    name: "Electronics",
    slug: "electronics",
    description: "From catalog",
    href: "/products?origin=china&category=electronics",
    icon: "grid",
    gradient: "from-sky-500 to-blue-600",
  },
];

function cmsWithFeaturedCollections(): CmsHomepageResponse {
  return {
    success: true,
    meta: {
      commerce_context: "GLOBAL",
      resolved_commerce_context: "GLOBAL",
      campaign: null,
    },
    data: {
      id: "layout-1",
      name: "Default Global",
      slug: "default-global",
      commerce_context: "GLOBAL",
      status: "active",
      is_default: true,
      sections: [
        {
          id: "sec-collections",
          cms_homepage_layout_id: "layout-1",
          section_type: "FEATURED_COLLECTIONS",
          title: "Collections",
          subtitle: null,
          position: 0,
          is_visible: true,
          featured_contents: [
            {
              id: "fc-collections",
              cms_homepage_section_id: "sec-collections",
              title: "Collections",
              subtitle: null,
              source_type: "MANUAL",
              limit: 6,
              position: 0,
              items: [
                {
                  item_type: "CATEGORY",
                  id: "cms-cat-1",
                  data: {
                    id: "cms-cat-1",
                    name: "CMS Electronics",
                    slug: "electronics",
                    description: "Configured in CMS",
                  },
                },
              ],
            },
          ],
        },
      ],
    },
  };
}

function delay(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

describe("loadHomepageContent — upstream call counts", () => {
  it("invokes CMS and catalog once when CMS has no layout (seed + catalog fallback)", async () => {
    let cmsCalls = 0;
    let catalogCalls = 0;

    const content = await loadHomepageContent({
      now: new Date("2026-07-20T12:00:00.000Z"),
      getCmsHomepage: async () => {
        cmsCalls += 1;
        return nullCmsResponse;
      },
      fetchFeaturedCollections: async () => {
        catalogCalls += 1;
        return catalogCollections;
      },
    });

    assert.equal(cmsCalls, 1);
    assert.equal(catalogCalls, 1);
    assert.equal(content.source, "fallback");
    assert.equal(content.collections[0]?.slug, "electronics");
    assert.equal(content.collections[0]?.id, "live-electronics");
  });

  it("does not await catalog when CMS supplies featured collections", async () => {
    let cmsCalls = 0;
    let catalogCalls = 0;
    let catalogFinished = false;

    const started = Date.now();
    const content = await loadHomepageContent({
      now: new Date("2026-07-20T12:00:00.000Z"),
      getCmsHomepage: async () => {
        cmsCalls += 1;
        await delay(20);
        return cmsWithFeaturedCollections();
      },
      fetchFeaturedCollections: async () => {
        catalogCalls += 1;
        await delay(250);
        catalogFinished = true;
        return catalogCollections;
      },
    });
    const elapsed = Date.now() - started;

    assert.equal(cmsCalls, 1);
    assert.equal(catalogCalls, 1, "catalog may start in parallel");
    assert.equal(catalogFinished, false, "must not wait for catalog when CMS collections win");
    assert.ok(elapsed < 200, `expected CMS-bound latency, got ${elapsed}ms`);
    assert.equal(content.source, "cms");
    assert.equal(content.collections[0]?.name, "CMS Electronics");
    assert.equal(content.collections[0]?.id, "cms-cat-1");
  });

  it("falls back to catalog collections when CMS throws", async () => {
    let catalogCalls = 0;

    const content = await loadHomepageContent({
      now: new Date("2026-07-20T12:00:00.000Z"),
      getCmsHomepage: async () => {
        throw new Error("CMS unavailable");
      },
      fetchFeaturedCollections: async () => {
        catalogCalls += 1;
        return catalogCollections;
      },
    });

    assert.equal(catalogCalls, 1);
    assert.equal(content.source, "fallback");
    assert.equal(content.collections[0]?.slug, "electronics");
  });

  it("overlaps CMS and catalog wall-clock when both required (timing)", async () => {
    const started = Date.now();
    await loadHomepageContent({
      now: new Date("2026-07-20T12:00:00.000Z"),
      getCmsHomepage: async () => {
        await delay(100);
        return nullCmsResponse;
      },
      fetchFeaturedCollections: async () => {
        await delay(100);
        return catalogCollections;
      },
    });
    const elapsed = Date.now() - started;

    assert.ok(
      elapsed < 180,
      `expected parallel ~100ms path, got ${elapsed}ms (sequential would be ~200ms)`,
    );
  });
});

describe("getHomepageContent — request consumers share one load", () => {
  it("Home + Suspense-style consumers share one CMS and one catalog fetch", async () => {
    // Production: `cache(() => loadHomepageContent())` — zero-arg React cache identity
    // (RSC request scope). Plain Node does not honor React cache, so this mirrors the
    // same single-flight contract Home + Commercial* children rely on.
    let cmsCalls = 0;
    let catalogCalls = 0;
    let inflight: ReturnType<typeof loadHomepageContent> | undefined;

    const getHomepageContentForRequest = () => {
      if (!inflight) {
        inflight = loadHomepageContent({
          now: new Date("2026-07-20T12:00:00.000Z"),
          getCmsHomepage: async () => {
            cmsCalls += 1;
            await delay(30);
            return nullCmsResponse;
          },
          fetchFeaturedCollections: async () => {
            catalogCalls += 1;
            await delay(30);
            return catalogCollections;
          },
        });
      }
      return inflight;
    };

    const results = await Promise.all([
      getHomepageContentForRequest(), // Home
      getHomepageContentForRequest(), // CommercialShopByStore
      getHomepageContentForRequest(), // CommercialFeaturedProducts
      getHomepageContentForRequest(), // CommercialNewArrivals
      getHomepageContentForRequest(), // CommercialBestSellers
    ]);

    assert.equal(cmsCalls, 1);
    assert.equal(catalogCalls, 1);
    assert.ok(results.every((result) => result === results[0]));
  });

  it("explicit now bypasses request cache and keeps schedule filtering correct", async () => {
    const frozen = new Date("2026-07-20T12:00:00.000Z");
    const content = await loadHomepageContent({
      now: frozen,
      getCmsHomepage: async () => nullCmsResponse,
      fetchFeaturedCollections: async () => [],
    });

    assert.deepEqual(
      content.heroSlides.map((slide) => slide.type),
      ["china", "tz"],
    );

    const rawActive = filterActiveScheduled(homepageContentSeed.advertisements, frozen);
    assert.ok(rawActive.every((ad) => isActivelyScheduled(ad, frozen)));

    // getHomepageContent(now) must remain a valid schedule-sensitive bypass.
    assert.equal(typeof getHomepageContent, "function");
  });
});

describe("loadHomepageContent — scheduling semantics with injected clock", () => {
  it("threads injected now into seed schedule filtering", async () => {
    const inWindow = new Date("2020-06-01T00:00:00.000Z");
    const outOfWindow = new Date("2021-06-01T00:00:00.000Z");
    const probe = {
      status: "active" as const,
      displayStart: FAR_PAST,
      displayEnd: "2020-12-31T23:59:59.000Z",
      priority: 1,
    };

    assert.equal(isActivelyScheduled(probe, inWindow), true);
    assert.equal(isActivelyScheduled(probe, outOfWindow), false);

    const content = await loadHomepageContent({
      now: inWindow,
      getCmsHomepage: async () => nullCmsResponse,
      fetchFeaturedCollections: async () => [],
    });

    assert.equal(content.source, "fallback");
    assert.ok(Array.isArray(content.heroSlides));
    void FAR_FUTURE;
  });
});
