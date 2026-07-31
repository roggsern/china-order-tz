import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { mergeTzStoreMedia } from "./merge-tz-store-media";

describe("mergeTzStoreMedia", () => {
  it("restores logo and banner fields from live Store Engine rows", () => {
    const merged = mergeTzStoreMedia(
      [
        {
          id: "cms-1",
          code: "ZION",
          name: "ZION MODE",
          slug: "zion-mode",
          theme_color: "#111111",
        },
      ],
      [
        {
          id: "live-1",
          code: "ZION",
          name: "ZION MODE",
          slug: "zion-mode",
          description: "Premium women's fashion",
          theme_color: "#1F4B3A",
          logo_url: "http://localhost:8000/storage/stores/zion-mode.svg",
          banner_url: null,
        },
      ],
    );

    assert.equal(merged[0]?.logo_url, "http://localhost:8000/storage/stores/zion-mode.svg");
    assert.equal(merged[0]?.description, "Premium women's fashion");
    assert.equal(merged[0]?.theme_color, "#111111");
  });

  it("returns live stores when CMS list is empty", () => {
    const live = [
      {
        id: "live-1",
        code: "ROVI",
        name: "ROVI BEAUTY",
        slug: "rovi-beauty",
        logo_url: "http://localhost:8000/storage/stores/rovi-beauty.svg",
      },
    ];

    assert.deepEqual(mergeTzStoreMedia([], live), live);
  });
});
