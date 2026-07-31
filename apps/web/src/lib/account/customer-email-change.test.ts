import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  mapEmailChangeConfirmSuccess,
  mapEmailChangeError,
  mapEmailChangeRequestSuccess,
  validateEmailChangeForm,
} from "@/lib/account/customer-email-change";

describe("customer email change form", () => {
  it("validates email change fields", () => {
    assert.equal(
      validateEmailChangeForm({ newEmail: "", currentPassword: "secret" }),
      "Please enter your new email address.",
    );
    assert.equal(
      validateEmailChangeForm({ newEmail: "bad", currentPassword: "secret" }),
      "Please enter a valid email address.",
    );
    assert.equal(
      validateEmailChangeForm({ newEmail: "new@example.com", currentPassword: "" }),
      "Please enter your current password.",
    );
    assert.equal(
      validateEmailChangeForm({
        newEmail: "new@example.com",
        currentPassword: "secret",
      }),
      null,
    );
  });

  it("maps success, pending, and error copy", () => {
    assert.match(mapEmailChangeRequestSuccess(null), /confirmation link/i);
    assert.equal(mapEmailChangeRequestSuccess("Pending ok"), "Pending ok");
    assert.match(mapEmailChangeConfirmSuccess(undefined), /updated/i);
    assert.match(mapEmailChangeError(null), /Unable to change email/i);
  });
});
