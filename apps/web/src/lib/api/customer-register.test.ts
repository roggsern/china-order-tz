import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { describe, it } from "node:test";
import {
  CustomerRegisterError,
  mapRegisterNetworkError,
} from "@/lib/api/customer-register";
import { toFriendlyAuthMessage } from "@/lib/auth/friendly-auth-messages";

describe("mapRegisterNetworkError", () => {
  it("maps Failed to fetch to a friendly sign-in hint", () => {
    const error = mapRegisterNetworkError(new TypeError("Failed to fetch"));

    assert.equal(error instanceof CustomerRegisterError, true);
    assert.match(error.message, /try signing in/i);
    assert.doesNotMatch(error.message, /failed to fetch/i);
  });

  it("maps AbortError-style timeouts", () => {
    const error = mapRegisterNetworkError(new Error("The operation was aborted."));

    assert.match(error.message, /try signing in|unable to create/i);
  });
});

describe("toFriendlyAuthMessage network mapping", () => {
  it("rewrites Failed to fetch for registration UX", () => {
    assert.match(
      toFriendlyAuthMessage("Failed to fetch"),
      /try signing in/i,
    );
  });
});

describe("auth register BFF route hardening", () => {
  it("uses AbortSignal timeout and controlled upstream failure JSON", () => {
    const source = readFileSync("src/app/api/auth/register/route.ts", "utf8");

    assert.match(source, /AbortController/);
    assert.match(source, /signal:\s*controller\.signal/);
    assert.match(source, /auth\.register\.upstream_unreachable/);
    assert.match(source, /status:\s*502/);
    assert.match(source, /try signing in/i);
    assert.doesNotMatch(source, /console\.log/);
  });
});

describe("RegisterForm submit mutex", () => {
  it("guards against duplicate submits while a request is in flight", () => {
    const source = readFileSync("src/components/auth/RegisterForm.tsx", "utf8");

    assert.match(source, /submitLockRef/);
    assert.match(source, /submitLockRef\.current\s*\|\|\s*isSubmitting/);
    assert.match(source, /submitLockRef\.current\s*=\s*true/);
  });
});
