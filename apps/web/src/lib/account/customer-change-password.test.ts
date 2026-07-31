import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  mapChangePasswordError,
  mapChangePasswordSuccess,
  validateChangePasswordForm,
} from "@/lib/account/customer-change-password";

describe("customer change password form", () => {
  it("validates required fields and confirmation", () => {
    assert.equal(
      validateChangePasswordForm({
        currentPassword: "",
        password: "password123",
        passwordConfirmation: "password123",
      }),
      "Please enter your current password.",
    );

    assert.equal(
      validateChangePasswordForm({
        currentPassword: "old-password",
        password: "short",
        passwordConfirmation: "short",
      }),
      "New password must be at least 8 characters.",
    );

    assert.equal(
      validateChangePasswordForm({
        currentPassword: "old-password",
        password: "password123",
        passwordConfirmation: "different",
      }),
      "Password confirmation does not match.",
    );

    assert.equal(
      validateChangePasswordForm({
        currentPassword: "same-password",
        password: "same-password",
        passwordConfirmation: "same-password",
      }),
      "New password must be different from your current password.",
    );

    assert.equal(
      validateChangePasswordForm({
        currentPassword: "old-password",
        password: "password123",
        passwordConfirmation: "password123",
      }),
      null,
    );
  });

  it("maps success and error copy", () => {
    assert.match(mapChangePasswordSuccess(null), /sign in again/i);
    assert.equal(mapChangePasswordSuccess("Custom ok"), "Custom ok");
    assert.match(mapChangePasswordError(undefined), /Unable to change password/i);
    assert.equal(mapChangePasswordError("Wrong password."), "Wrong password.");
  });
});
