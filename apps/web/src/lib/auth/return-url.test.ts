import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  buildLoginHref,
  resolvePostAuthRedirect,
  sanitizeReturnUrl,
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

describe("resolvePostAuthRedirect", () => {
  it("returns checkout for checkout-origin auth", () => {
    assert.equal(resolvePostAuthRedirect("/checkout"), "/checkout");
  });

  it("falls back to account for invalid return URLs", () => {
    assert.equal(resolvePostAuthRedirect("https://evil.test"), "/account");
  });
});

describe("buildLoginHref", () => {
  it("preserves checkout return URL in login link", () => {
    assert.equal(buildLoginHref("/checkout"), "/login?returnUrl=%2Fcheckout");
  });
});
