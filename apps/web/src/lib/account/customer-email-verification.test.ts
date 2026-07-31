import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  mapResendVerificationSuccess,
  mapVerifyEmailError,
  mapVerifyEmailSuccess,
  parseVerifyEmailQuery,
  validateVerifyEmailQuery,
} from "@/lib/account/customer-email-verification";

describe("customer email verification", () => {
  it("parses and validates verification query state", () => {
    const incomplete = parseVerifyEmailQuery(new URLSearchParams("id=abc"));
    assert.equal(
      validateVerifyEmailQuery(incomplete),
      "This verification link is incomplete. Request a new verification email.",
    );

    const complete = parseVerifyEmailQuery(
      new URLSearchParams("id=u1&hash=h1&expires=1&signature=s1"),
    );
    assert.equal(validateVerifyEmailQuery(complete), null);
    assert.equal(complete.id, "u1");
  });

  it("maps verify and resend copy", () => {
    assert.match(mapVerifyEmailSuccess(null, false), /verified/i);
    assert.match(mapVerifyEmailSuccess(null, true), /already verified/i);
    assert.equal(mapVerifyEmailSuccess("Custom"), "Custom");
    assert.match(mapVerifyEmailError(undefined), /Unable to verify/i);
    assert.match(mapResendVerificationSuccess(null), /verification link/i);
  });
});
