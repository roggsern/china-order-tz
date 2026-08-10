import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  buildUnifiedSearchProductsQuery,
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

  it("maps Buy from TZ tab to scope=tz", () => {
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

describe("buildUnifiedSearchProductsQuery", () => {
  it("sends q, scope, page, and per_page for unified results", () => {
    const params = buildUnifiedSearchProductsQuery({
      q: "zion",
      scope: "all",
      page: 2,
      perPage: 24,
      sort: "relevance",
    });
    assert.equal(params.get("q"), "zion");
    assert.equal(params.get("scope"), "all");
    assert.equal(params.get("page"), "2");
    assert.equal(params.get("per_page"), "24");
    assert.equal(params.get("sort"), "relevance");
  });

  it("preserves China scope on products search params", () => {
    const params = buildUnifiedSearchProductsQuery({
      q: "UPS",
      scope: "china",
    });
    assert.equal(params.get("q"), "UPS");
    assert.equal(params.get("scope"), "china");
  });
});
