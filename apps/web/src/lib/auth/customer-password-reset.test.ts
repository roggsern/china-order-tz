import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  mapForgotPasswordSuccess,
  mapResetPasswordSuccess,
  parseResetPasswordQuery,
  validateForgotPasswordEmail,
  validateResetPasswordForm,
} from "@/lib/auth/customer-password-reset";

describe("customer password reset form mapping", () => {
  it("validates forgot-password email submission", () => {
    assert.equal(validateForgotPasswordEmail(""), "Please enter your email address.");
    assert.equal(validateForgotPasswordEmail("bad"), "Please enter a valid email address.");
    assert.equal(validateForgotPasswordEmail("user@example.com"), null);
  });

  it("validates reset form fields and confirmation", () => {
    assert.match(
      validateResetPasswordForm({
        email: "user@example.com",
        token: "",
        password: "password123",
        passwordConfirmation: "password123",
      }) ?? "",
      /missing a token/i,
    );

    assert.equal(
      validateResetPasswordForm({
        email: "user@example.com",
        token: "abc",
        password: "short",
        passwordConfirmation: "short",
      }),
      "Password must be at least 8 characters.",
    );

    assert.equal(
      validateResetPasswordForm({
        email: "user@example.com",
        token: "abc",
        password: "password123",
        passwordConfirmation: "different",
      }),
      "Password confirmation does not match.",
    );

    assert.equal(
      validateResetPasswordForm({
        email: "user@example.com",
        token: "abc",
        password: "password123",
        passwordConfirmation: "password123",
      }),
      null,
    );
  });

  it("maps success and error-facing copy", () => {
    assert.match(mapForgotPasswordSuccess(null), /If an account exists/i);
    assert.equal(mapForgotPasswordSuccess("Custom ok"), "Custom ok");
    assert.match(mapResetPasswordSuccess(undefined), /password has been reset/i);
  });

  it("parses reset query token and email", () => {
    const params = new URLSearchParams(
      "token=reset-token&email=user%40example.com&returnUrl=%2Faccount",
    );
    const parsed = parseResetPasswordQuery(params);
    assert.equal(parsed.token, "reset-token");
    assert.equal(parsed.email, "user@example.com");
  });
});
