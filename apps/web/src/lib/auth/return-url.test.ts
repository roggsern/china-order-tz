import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  buildCurrentReturnPath,
  buildLoginHref,
  resolveAuthEntryHref,
  resolvePostAuthRedirect,
  sanitizeReturnUrl,
  withPreservedReturnUrl,
} from "./return-url";

describe("sanitizeReturnUrl", () => {
  it("accepts safe internal checkout paths", () => {
    assert.equal(sanitizeReturnUrl("/checkout"), "/checkout");
    assert.equal(sanitizeReturnUrl("%2Fcheckout"), "/checkout");
  });

  it("rejects external and protocol-relative redirects", () => {
    assert.equal(sanitizeReturnUrl("https://evil.test/checkout"), null);
    assert.equal(sanitizeReturnUrl("//evil.test/checkout"), null);
  });

  it("rejects auth page loops", () => {
    assert.equal(sanitizeReturnUrl("/login"), null);
    assert.equal(sanitizeReturnUrl("/register"), null);
  });
});

describe("buildCurrentReturnPath", () => {
  it("preserves payment return path and query", () => {
    assert.equal(
      buildCurrentReturnPath("/payment/return", "resultIndicator=abc123"),
      "/payment/return?resultIndicator=abc123",
    );
    assert.equal(
      buildCurrentReturnPath("/payment/return", "?resultIndicator=abc123"),
      "/payment/return?resultIndicator=abc123",
    );
  });

  it("returns null on auth pages", () => {
    assert.equal(buildCurrentReturnPath("/login"), null);
    assert.equal(buildCurrentReturnPath("/register", "ref=1"), null);
  });
});

describe("resolvePostAuthRedirect", () => {
  it("returns checkout for checkout-origin auth", () => {
    assert.equal(resolvePostAuthRedirect("/checkout"), "/checkout");
  });

  it("returns payment return with query after login", () => {
    assert.equal(
      resolvePostAuthRedirect("/payment/return?resultIndicator=abc123"),
      "/payment/return?resultIndicator=abc123",
    );
  });

  it("falls back to account for invalid return URLs", () => {
    assert.equal(resolvePostAuthRedirect("https://evil.test"), "/account");
  });
});

describe("buildLoginHref", () => {
  it("preserves checkout return URL in login link", () => {
    assert.equal(buildLoginHref("/checkout"), "/login?returnUrl=%2Fcheckout");
  });

  it("preserves payment return query in login link", () => {
    assert.equal(
      buildLoginHref("/payment/return?resultIndicator=abc123"),
      "/login?returnUrl=%2Fpayment%2Freturn%3FresultIndicator%3Dabc123",
    );
  });
});

describe("resolveAuthEntryHref", () => {
  it("rewrites bare login and register with return context", () => {
    const returnPath = "/payment/return?resultIndicator=abc123";
    assert.equal(
      resolveAuthEntryHref("/login", returnPath),
      buildLoginHref(returnPath),
    );
    assert.equal(
      resolveAuthEntryHref("/register", returnPath),
      `/register?returnUrl=${encodeURIComponent(returnPath)}`,
    );
  });

  it("leaves non-auth hrefs unchanged", () => {
    assert.equal(resolveAuthEntryHref("/orders", "/payment/return"), "/orders");
  });
});

describe("withPreservedReturnUrl", () => {
  it("supports internal account routes as href targets", () => {
    assert.equal(
      withPreservedReturnUrl("/account/security", "/checkout"),
      "/account/security?returnUrl=%2Fcheckout",
    );
  });

  it("still sanitizes returnUrl and omits query when unsafe", () => {
    assert.equal(withPreservedReturnUrl("/login", "https://evil.test"), "/login");
  });
});
