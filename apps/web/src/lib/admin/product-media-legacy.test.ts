import assert from "node:assert/strict";
import { test } from "node:test";
import { mapAdminApiProductMedia } from "@/lib/api/admin-catalog";
import {
  hasLegacyMediaItems,
  isLegacyMediaItem,
  LEGACY_MEDIA_HELPER_TEXT,
  supportsCatalogMediaActions,
} from "./product-media-legacy";

test("mapAdminApiProductMedia maps is_legacy from API payload", () => {
  assert.deepEqual(
    mapAdminApiProductMedia({
      id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
      product_id: "019f7a6e-1111-7376-aca4-aed79f33519b",
      type: "image",
      url: "/storage/demo-products/phone.jpg",
      is_legacy: true,
    }),
    {
      id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
      productId: "019f7a6e-1111-7376-aca4-aed79f33519b",
      productVariantId: null,
      variantName: null,
      type: "image",
      url: "/storage/demo-products/phone.jpg",
      thumbnailUrl: null,
      altText: "",
      title: "",
      sortOrder: 0,
      isPrimary: false,
      isActive: true,
      isLegacy: true,
    },
  );

  assert.equal(
    mapAdminApiProductMedia({
      id: "019f7a6e-2222-7376-aca4-aed79f33519b",
      product_id: "019f7a6e-1111-7376-aca4-aed79f33519b",
      type: "image",
      url: "/storage/demo-products/shoes.jpg",
    }).isLegacy,
    false,
  );
});

test("legacy media helpers distinguish bridged items", () => {
  const legacy = { isLegacy: true as const };
  const catalog = { isLegacy: false as const };

  assert.equal(isLegacyMediaItem(legacy), true);
  assert.equal(isLegacyMediaItem(catalog), false);
  assert.equal(supportsCatalogMediaActions(legacy), false);
  assert.equal(supportsCatalogMediaActions(catalog), true);
  assert.equal(hasLegacyMediaItems([catalog]), false);
  assert.equal(hasLegacyMediaItems([catalog, legacy]), true);
  assert.match(LEGACY_MEDIA_HELPER_TEXT, /previous media system/i);
});
