import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  buildUnifiedSuggestQuery,
  resolveUnifiedSuggestScope,
} from "./marketplace-search";

describe("resolveUnifiedSuggestScope", () => {
  it("maps All scope to unified suggest scope=all", () => {
    assert.equal(resolveUnifiedSuggestScope("all"), "all");
  });

  it("maps China tab to scope=china", () => {
    assert.equal(resolveUnifiedSuggestScope("china"), "china");
  });

  it("maps Buy from Dar tab to scope=tz", () => {
    assert.equal(resolveUnifiedSuggestScope("tz"), "tz");
  });
});

describe("buildUnifiedSuggestQuery", () => {
  it("All scope calls unified suggest with scope=all", () => {
    const params = buildUnifiedSuggestQuery({ q: "zion", scope: "all" });
    assert.equal(params.get("q"), "zion");
    assert.equal(params.get("scope"), "all");
  });

  it("China scope sends scope=china", () => {
    const params = buildUnifiedSuggestQuery({ q: "zion", scope: "china" });
    assert.equal(params.get("scope"), "china");
    assert.equal(params.get("q"), "zion");
  });

  it("TZ scope sends scope=tz", () => {
    const params = buildUnifiedSuggestQuery({ q: "zion", scope: "tz" });
    assert.equal(params.get("scope"), "tz");
  });
});
