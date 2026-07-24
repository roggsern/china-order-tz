import assert from "node:assert/strict";
import { test } from "node:test";
import {
  buildCatalogProductEditUrl,
  catalogProductIdMatchesLegacyNumericId,
  legacyNumericIdFromCatalogProductId,
  parseCatalogProductEditTab,
  resolveCatalogProductIdFromLegacyNumericId,
} from "./product-id-map";

test("legacyNumericIdFromCatalogProductId is deterministic", () => {
  const uuid = "019f7a6e-4d46-7376-aca4-aed79f33519b";
  const first = legacyNumericIdFromCatalogProductId(uuid);
  const second = legacyNumericIdFromCatalogProductId(uuid);

  assert.equal(first, second);
  assert.ok(first > 0);
});

test("resolveCatalogProductIdFromLegacyNumericId maps numeric id to uuid", () => {
  const catalogProductId = "019f7a6e-4d46-7376-aca4-aed79f33519b";
  const legacyNumericId = legacyNumericIdFromCatalogProductId(catalogProductId);

  assert.equal(
    resolveCatalogProductIdFromLegacyNumericId(legacyNumericId, [
      { id: legacyNumericId, catalogProductId },
      { id: 999, catalogProductId: "other-uuid" },
    ]),
    catalogProductId,
  );

  assert.equal(
    catalogProductIdMatchesLegacyNumericId(catalogProductId, legacyNumericId),
    true,
  );
});

test("buildCatalogProductEditUrl encodes edit and optional tab", () => {
  const uuid = "019f7a6e-4d46-7376-aca4-aed79f33519b";

  assert.equal(
    buildCatalogProductEditUrl(uuid),
    `/admin/products?edit=${encodeURIComponent(uuid)}`,
  );
  assert.equal(
    buildCatalogProductEditUrl(uuid, "media"),
    `/admin/products?edit=${encodeURIComponent(uuid)}&tab=media`,
  );
  assert.equal(
    buildCatalogProductEditUrl(uuid, "shipping"),
    `/admin/products?edit=${encodeURIComponent(uuid)}&tab=shipping`,
  );
  assert.equal(
    buildCatalogProductEditUrl(uuid, "stock"),
    `/admin/products?edit=${encodeURIComponent(uuid)}&tab=stock`,
  );
});

test("parseCatalogProductEditTab falls back to details", () => {
  assert.equal(parseCatalogProductEditTab("variants"), "variants");
  assert.equal(parseCatalogProductEditTab("shipping"), "shipping");
  assert.equal(parseCatalogProductEditTab("stock"), "stock");
  assert.equal(parseCatalogProductEditTab("invalid"), "details");
});
