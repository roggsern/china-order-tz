import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  filterAcceptedProductMediaFiles,
  productMediaDropActiveClass,
  validateProductMediaUpload,
} from "@/lib/admin/product-media-upload";

function fakeFile(name: string, type: string, size = 1024): File {
  const buffer = new Uint8Array(size);
  return new File([buffer], name, { type });
}

describe("product-media-upload", () => {
  it("accepts jpg png webp and rejects others", () => {
    const accepted = filterAcceptedProductMediaFiles([
      fakeFile("a.jpg", "image/jpeg"),
      fakeFile("b.png", "image/png"),
      fakeFile("c.webp", "image/webp"),
      fakeFile("d.gif", "image/gif"),
    ]);

    assert.equal(accepted.length, 3);
  });

  it("validates empty, oversized, and mixed uploads", () => {
    assert.equal(validateProductMediaUpload([]).error, null);

    const oversized = validateProductMediaUpload([
      fakeFile("big.jpg", "image/jpeg", 6 * 1024 * 1024),
    ]);
    assert.equal(oversized.accepted.length, 0);
    assert.match(oversized.error ?? "", /5MB/i);

    const mixed = validateProductMediaUpload([
      fakeFile("ok.png", "image/png", 1000),
      fakeFile("bad.gif", "image/gif", 1000),
    ]);
    assert.equal(mixed.accepted.length, 1);
    assert.match(mixed.error ?? "", /skipped/i);
  });

  it("returns distinct drop-zone classes for drag state", () => {
    assert.match(productMediaDropActiveClass(true), /c9a227/);
    assert.match(productMediaDropActiveClass(false), /zinc-300/);
  });
});
