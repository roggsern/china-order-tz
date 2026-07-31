import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  VARIANT_MEDIA_EMPTY_STATE,
  buildVariantMediaUploadFields,
  buildVariantMediaUploadOptions,
  canManageVariantMedia,
  countVariantImages,
  formatVariantImageCount,
  formatVariantMediaEditingLabel,
  resolveVariantMediaListLabel,
} from "@/lib/admin/variant-media";
import { mapAdminApiProductMedia } from "@/lib/api/admin-catalog";

describe("variant media helpers", () => {
  it("renders editing label and empty-state copy", () => {
    assert.equal(
      formatVariantMediaEditingLabel("Black / Medium"),
      "Editing images for:\nBlack / Medium",
    );
    assert.match(VARIANT_MEDIA_EMPTY_STATE, /Product images will be used as fallback/);
  });

  it("formats image counts and resolves list labels", () => {
    assert.equal(formatVariantImageCount(0), "0 images");
    assert.equal(formatVariantImageCount(1), "1 image");
    assert.equal(formatVariantImageCount(3), "3 images");
    assert.equal(resolveVariantMediaListLabel({ name: "Black / Medium", sku: "SKU-1" }), "Black / Medium");
    assert.equal(resolveVariantMediaListLabel({ name: null, sku: "SKU-1" }), "SKU-1");
  });

  it("gates manage actions on catalog.update", () => {
    assert.equal(canManageVariantMedia(undefined), true);
    assert.equal(canManageVariantMedia(["catalog.update"]), true);
    assert.equal(canManageVariantMedia(["catalog.view"]), false);
    assert.equal(canManageVariantMedia([]), false);
  });

  it("builds upload payload with product_variant_id", () => {
    assert.deepEqual(buildVariantMediaUploadFields("variant-123"), {
      product_variant_id: "variant-123",
    });

    assert.deepEqual(
      buildVariantMediaUploadOptions({
        productVariantId: "variant-123",
        variantLabel: "Black / Medium",
        existingImageCount: 0,
        fileIndex: 0,
      }),
      {
        title: "Black / Medium image",
        isPrimary: true,
        sortOrder: 0,
        productVariantId: "variant-123",
      },
    );

    assert.deepEqual(
      buildVariantMediaUploadOptions({
        productVariantId: "variant-123",
        variantLabel: "Black / Medium",
        existingImageCount: 2,
        fileIndex: 1,
      }),
      {
        title: "Black / Medium image",
        isPrimary: false,
        sortOrder: 3,
        productVariantId: "variant-123",
      },
    );
  });

  it("counts images and maps API variant media fields", () => {
    const mapped = mapAdminApiProductMedia({
      id: "media-1",
      product_id: "product-1",
      product_variant_id: "variant-1",
      variant_name: "Black / Medium",
      type: "image",
      url: "/storage/a.jpg",
      is_primary: true,
      is_active: true,
      sort_order: 0,
    });

    assert.equal(mapped.productVariantId, "variant-1");
    assert.equal(mapped.variantName, "Black / Medium");
    assert.equal(
      countVariantImages([
        mapped,
        {
          ...mapped,
          id: "media-2",
          type: "video",
        },
      ]),
      1,
    );
  });

  it("supports delete intent by counting after removal", () => {
    const before = [
      {
        id: "a",
        productId: "p",
        productVariantId: "v",
        variantName: "Black",
        type: "image" as const,
        url: "/a.jpg",
        thumbnailUrl: null,
        altText: "",
        title: "",
        sortOrder: 0,
        isPrimary: true,
        isActive: true,
        isLegacy: false,
      },
      {
        id: "b",
        productId: "p",
        productVariantId: "v",
        variantName: "Black",
        type: "image" as const,
        url: "/b.jpg",
        thumbnailUrl: null,
        altText: "",
        title: "",
        sortOrder: 1,
        isPrimary: false,
        isActive: true,
        isLegacy: false,
      },
    ];

    const afterDelete = before.filter((item) => item.id !== "a");
    assert.equal(countVariantImages(before), 2);
    assert.equal(countVariantImages(afterDelete), 1);
    assert.equal(formatVariantImageCount(countVariantImages(afterDelete)), "1 image");
  });
});
