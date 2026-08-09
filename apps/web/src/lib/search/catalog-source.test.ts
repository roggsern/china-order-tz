import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { resolveLiveSearchProductSource } from "./catalog-source";

describe("resolveLiveSearchProductSource", () => {
  it("routes China scope to the China storefront products API", () => {
    assert.equal(resolveLiveSearchProductSource("china"), "china-storefront");
  });

  it("keeps All and TZ scopes on the generic catalog search API", () => {
    assert.equal(resolveLiveSearchProductSource(undefined), "catalog");
    assert.equal(resolveLiveSearchProductSource("tz"), "catalog");
  });
});
