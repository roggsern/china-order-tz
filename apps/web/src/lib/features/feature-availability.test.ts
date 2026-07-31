import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  DEFAULT_PUBLIC_FEATURE_FLAGS,
  isFeatureDisabledResponse,
  mapPublicFeatureFlags,
} from "@/lib/features/feature-availability";

describe("feature availability mapping", () => {
  it("maps nested API payloads into public flags", () => {
    assert.deepEqual(
      mapPublicFeatureFlags({
        success: true,
        data: { wishlist: true, reviews: false, new_checkout: true },
      }),
      {
        wishlist: true,
        reviews: false,
        new_checkout: true,
      },
    );
  });

  it("defaults missing flags to disabled", () => {
    assert.deepEqual(mapPublicFeatureFlags(undefined), DEFAULT_PUBLIC_FEATURE_FLAGS);
    assert.deepEqual(mapPublicFeatureFlags({ success: true, data: {} }), DEFAULT_PUBLIC_FEATURE_FLAGS);
  });

  it("never trusts forbidden keys in raw payloads", () => {
    const mapped = mapPublicFeatureFlags({
      success: true,
      data: {
        wishlist: true,
        payment_verification: true,
      } as Record<string, boolean>,
    });

    assert.equal(mapped.wishlist, true);
    assert.equal("payment_verification" in mapped, false);
    assert.deepEqual(Object.keys(mapped).sort(), ["new_checkout", "reviews", "wishlist"]);
  });

  it("detects feature_disabled API responses", () => {
    assert.equal(
      isFeatureDisabledResponse(403, { code: "feature_disabled", feature: "wishlist" }),
      true,
    );
    assert.equal(isFeatureDisabledResponse(403, { code: "forbidden" }), false);
    assert.equal(isFeatureDisabledResponse(200, { code: "feature_disabled" }), false);
  });
});

describe("feature visibility decisions", () => {
  it("hides wishlist UI when the public flag is off", () => {
    const flags = mapPublicFeatureFlags({ data: { wishlist: false, reviews: true, new_checkout: false } });
    assert.equal(flags.wishlist, false);
  });

  it("hides review UI when the public flag is off", () => {
    const flags = mapPublicFeatureFlags({ data: { wishlist: true, reviews: false, new_checkout: false } });
    assert.equal(flags.reviews, false);
  });
});
