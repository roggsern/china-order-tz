import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  ADMIN_PRODUCT_MEDIA_HEIC_MESSAGE,
  ADMIN_PRODUCT_MEDIA_MAX_BYTES,
  ADMIN_PRODUCT_MEDIA_SIZE_MESSAGE,
  filterAcceptedProductMediaFiles,
  productMediaDropActiveClass,
  resolveProductMediaUploadError,
  validateProductMediaUpload,
} from "@/lib/admin/product-media-upload";

function fakeFile(name: string, type: string, size = 1024): File {
  const buffer = new Uint8Array(size);
  return new File([buffer], name, { type });
}

describe("product-media-upload", () => {
  it("accepts jpg png webp under 10 MB and rejects others", () => {
    const accepted = filterAcceptedProductMediaFiles([
      fakeFile("a.jpg", "image/jpeg"),
      fakeFile("b.png", "image/png"),
      fakeFile("c.webp", "image/webp"),
      fakeFile("d.gif", "image/gif"),
      fakeFile("e.heic", "image/heic"),
    ]);

    assert.equal(accepted.length, 3);
    assert.equal(ADMIN_PRODUCT_MEDIA_MAX_BYTES, 10 * 1024 * 1024);
  });

  it("rejects HEIC/HEIF with explicit guidance", () => {
    const byExt = validateProductMediaUpload([fakeFile("photo.HEIC", "")]);
    assert.equal(byExt.accepted.length, 0);
    assert.equal(byExt.error, ADMIN_PRODUCT_MEDIA_HEIC_MESSAGE);

    const byMime = validateProductMediaUpload([
      fakeFile("photo.bin", "image/heif", 2048),
    ]);
    assert.equal(byMime.accepted.length, 0);
    assert.equal(byMime.error, ADMIN_PRODUCT_MEDIA_HEIC_MESSAGE);
  });

  it("rejects over 10 MB with clear size message", () => {
    assert.equal(validateProductMediaUpload([]).error, null);

    const oversized = validateProductMediaUpload([
      fakeFile("big.jpg", "image/jpeg", 10 * 1024 * 1024 + 1),
    ]);
    assert.equal(oversized.accepted.length, 0);
    assert.equal(oversized.error, ADMIN_PRODUCT_MEDIA_SIZE_MESSAGE);

    const justUnder = validateProductMediaUpload([
      fakeFile("ok.jpg", "image/jpeg", 10 * 1024 * 1024),
    ]);
    assert.equal(justUnder.accepted.length, 1);
    assert.equal(justUnder.error, null);
  });

  it("keeps valid files when mixed with unsupported types", () => {
    const mixed = validateProductMediaUpload([
      fakeFile("ok.png", "image/png", 1000),
      fakeFile("bad.gif", "image/gif", 1000),
    ]);
    assert.equal(mixed.accepted.length, 1);
    assert.match(mixed.error ?? "", /skipped/i);
  });

  it("preserves Laravel field validation messages for BFF surfacing", () => {
    assert.equal(
      resolveProductMediaUploadError(422, {
        errors: { file: ["Image exceeds the 10 MB upload limit."] },
        message: "The given data was invalid.",
      }),
      "Image exceeds the 10 MB upload limit.",
    );

    assert.match(resolveProductMediaUploadError(413, {}), /10 MB/i);
    assert.match(resolveProductMediaUploadError(0, {}), /Network error/i);
    assert.equal(
      resolveProductMediaUploadError(500, {}, "Unable to upload image."),
      "Unable to upload image.",
    );
  });

  it("returns distinct drop-zone classes for drag state", () => {
    assert.match(productMediaDropActiveClass(true), /c9a227/);
    assert.match(productMediaDropActiveClass(false), /zinc-300/);
  });
});
