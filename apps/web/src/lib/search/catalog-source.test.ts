import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  resolveLiveSearchBrandSource,
  resolveLiveSearchProductSource,
} from "./catalog-source";

describe("resolveLiveSearchProductSource", () => {
  it("routes China scope to the China storefront products API", () => {
    assert.equal(resolveLiveSearchProductSource("china"), "china-storefront");
  });

  it("keeps All and TZ scopes on the generic catalog search API", () => {
    assert.equal(resolveLiveSearchProductSource(undefined), "catalog");
    assert.equal(resolveLiveSearchProductSource("tz"), "catalog");
  });
});

describe("resolveLiveSearchBrandSource", () => {
  it("routes China scope to China storefront brands (same as filters)", () => {
    assert.equal(resolveLiveSearchBrandSource("china"), "china-storefront");
  });

  it("keeps All and TZ scopes on the generic catalog brands API", () => {
    assert.equal(resolveLiveSearchBrandSource(undefined), "catalog");
    assert.equal(resolveLiveSearchBrandSource("tz"), "catalog");
  });
});
