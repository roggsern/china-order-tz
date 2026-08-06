import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  ADMIN_PRODUCT_MEDIA_FORMAT_MESSAGE,
  ADMIN_PRODUCT_MEDIA_HEIC_MESSAGE,
  ADMIN_PRODUCT_MEDIA_MAX_BYTES,
  ADMIN_PRODUCT_MEDIA_SIZE_MESSAGE,
  filterAcceptedProductMediaFiles,
  isAcceptedProductMediaFile,
  isAcceptedProductMediaFileAsync,
  normalizeProductMediaMime,
  productMediaDropActiveClass,
  resolveProductMediaUploadError,
  sniffProductMediaKind,
  validateProductMediaUpload,
} from "@/lib/admin/product-media-upload";

function fakeFile(name: string, type: string, sizeOrBytes: number | Uint8Array = 1024): File {
  if (sizeOrBytes instanceof Uint8Array) {
    return new File([sizeOrBytes], name, { type });
  }

  const buffer = new Uint8Array(sizeOrBytes);
  return new File([buffer], name, { type });
}

function pngBytes(extra = 0): Uint8Array {
  const signature = [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a];
  const bytes = new Uint8Array(signature.length + extra);
  bytes.set(signature);
  return bytes;
}

function jpegBytes(extra = 0): Uint8Array {
  const signature = [0xff, 0xd8, 0xff, 0xe0];
  const bytes = new Uint8Array(signature.length + extra);
  bytes.set(signature);
  return bytes;
}

function webpBytes(): Uint8Array {
  // RIFF....WEBP
  return new Uint8Array([
    0x52, 0x49, 0x46, 0x46, 0x00, 0x00, 0x00, 0x00, 0x57, 0x45, 0x42, 0x50,
  ]);
}

function heicBytes(): Uint8Array {
  // size(4) + ftyp + heic
  return new Uint8Array([
    0x00, 0x00, 0x00, 0x18, 0x66, 0x74, 0x79, 0x70, 0x68, 0x65, 0x69, 0x63,
  ]);
}

describe("product-media-upload", () => {
  it("normalizes MIME aliases and sync-accepts jpg png webp", () => {
    assert.equal(normalizeProductMediaMime(" Image/PNG "), "image/png");
    assert.equal(isAcceptedProductMediaFile(fakeFile("a.jpg", "image/jpeg")), true);
    assert.equal(isAcceptedProductMediaFile(fakeFile("b.png", "image/x-png")), true);
    assert.equal(isAcceptedProductMediaFile(fakeFile("c.webp", "image/webp")), true);
    assert.equal(isAcceptedProductMediaFile(fakeFile("d.gif", "image/gif")), false);
    assert.equal(isAcceptedProductMediaFile(fakeFile("e.heic", "image/heic")), false);
    assert.equal(ADMIN_PRODUCT_MEDIA_MAX_BYTES, 10 * 1024 * 1024);

    const accepted = filterAcceptedProductMediaFiles([
      fakeFile("a.jpg", "image/jpeg"),
      fakeFile("b.png", "image/png"),
      fakeFile("c.webp", "image/webp"),
      fakeFile("d.gif", "image/gif"),
    ]);
    assert.equal(accepted.length, 3);
  });

  it("accepts 1.9 MB image/png with no extension", async () => {
    const result = await validateProductMediaUpload([
      fakeFile("IMG_CHAT", "image/png", Math.round(1.9 * 1024 * 1024)),
    ]);
    assert.equal(result.accepted.length, 1);
    assert.equal(result.error, null);
  });

  it("accepts empty MIME and octet-stream when extension is .png", async () => {
    const empty = await validateProductMediaUpload([fakeFile("photo.png", "", 2048)]);
    assert.equal(empty.accepted.length, 1);
    assert.equal(empty.error, null);

    const octet = await validateProductMediaUpload([
      fakeFile("photo.png", "application/octet-stream", 2048),
    ]);
    assert.equal(octet.accepted.length, 1);
    assert.equal(octet.error, null);
  });

  it("accepts image/x-png extensionless files", async () => {
    const result = await validateProductMediaUpload([
      fakeFile("IMG_1234", "image/x-png", 2048),
    ]);
    assert.equal(result.accepted.length, 1);
    assert.equal(result.error, null);
  });

  it("accepts empty MIME + extensionless PNG/JPEG/WebP via magic bytes", async () => {
    const png = await validateProductMediaUpload([fakeFile("IMG_A", "", pngBytes())]);
    assert.equal(png.accepted.length, 1);
    assert.equal(png.error, null);
    assert.equal(await sniffProductMediaKind(fakeFile("x", "", pngBytes())), "png");

    const jpeg = await validateProductMediaUpload([fakeFile("IMG_B", "", jpegBytes())]);
    assert.equal(jpeg.accepted.length, 1);
    assert.equal(jpeg.error, null);

    const webp = await validateProductMediaUpload([fakeFile("IMG_C", "", webpBytes())]);
    assert.equal(webp.accepted.length, 1);
    assert.equal(webp.error, null);

    assert.equal(await isAcceptedProductMediaFileAsync(fakeFile("IMG_A", "", pngBytes())), true);
  });

  it("rejects unsupported bytes with format-only message (no 10 MB)", async () => {
    const result = await validateProductMediaUpload([
      fakeFile("IMG_BAD", "", new Uint8Array([0x00, 0x01, 0x02, 0x03, 0x04])),
    ]);
    assert.equal(result.accepted.length, 0);
    assert.equal(result.error, ADMIN_PRODUCT_MEDIA_FORMAT_MESSAGE);
    assert.doesNotMatch(result.error ?? "", /10\s*MB/i);
  });

  it("rejects over 10 MB with exact size message", async () => {
    assert.equal((await validateProductMediaUpload([])).error, null);

    const oversized = await validateProductMediaUpload([
      fakeFile("big.png", "image/png", ADMIN_PRODUCT_MEDIA_MAX_BYTES + 1),
    ]);
    assert.equal(oversized.accepted.length, 0);
    assert.equal(oversized.error, ADMIN_PRODUCT_MEDIA_SIZE_MESSAGE);

    const justUnder = await validateProductMediaUpload([
      fakeFile("ok.png", "image/png", ADMIN_PRODUCT_MEDIA_MAX_BYTES),
    ]);
    assert.equal(justUnder.accepted.length, 1);
    assert.equal(justUnder.error, null);
  });

  it("rejects HEIC by extension, MIME, and signature", async () => {
    const byExt = await validateProductMediaUpload([fakeFile("photo.HEIC", "")]);
    assert.equal(byExt.accepted.length, 0);
    assert.equal(byExt.error, ADMIN_PRODUCT_MEDIA_HEIC_MESSAGE);
    assert.doesNotMatch(byExt.error ?? "", /10\s*MB/i);

    const byMime = await validateProductMediaUpload([
      fakeFile("photo.bin", "image/heif", 2048),
    ]);
    assert.equal(byMime.accepted.length, 0);
    assert.equal(byMime.error, ADMIN_PRODUCT_MEDIA_HEIC_MESSAGE);

    const bySig = await validateProductMediaUpload([fakeFile("IMG_HEIC", "", heicBytes())]);
    assert.equal(bySig.accepted.length, 0);
    assert.equal(bySig.error, ADMIN_PRODUCT_MEDIA_HEIC_MESSAGE);
    assert.equal(await sniffProductMediaKind(fakeFile("x", "", heicBytes())), "heic");
  });

  it("keeps valid files when mixed with unsupported types", async () => {
    const mixed = await validateProductMediaUpload([
      fakeFile("ok.png", "image/png", 1000),
      fakeFile("bad.gif", "image/gif", 1000),
    ]);
    assert.equal(mixed.accepted.length, 1);
    assert.match(mixed.error ?? "", /skipped/i);
    assert.doesNotMatch(mixed.error ?? "", /10\s*MB/i);
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
    assert.equal(
      resolveProductMediaUploadError(415, {}),
      ADMIN_PRODUCT_MEDIA_FORMAT_MESSAGE,
    );
    assert.match(resolveProductMediaUploadError(0, {}), /Network error/i);
  });

  it("returns distinct drop-zone classes for drag state", () => {
    assert.match(productMediaDropActiveClass(true), /c9a227/);
    assert.match(productMediaDropActiveClass(false), /zinc-300/);
  });
});
