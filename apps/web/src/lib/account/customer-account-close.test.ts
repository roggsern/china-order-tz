import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  mapCloseAccountError,
  mapCloseAccountSuccess,
  validateCloseAccountForm,
} from "./customer-account-close";

describe("customer-account-close helpers", () => {
  it("requires password and acknowledgement", () => {
    assert.ok(validateCloseAccountForm({ currentPassword: "", acknowledge: true }));
    assert.ok(validateCloseAccountForm({ currentPassword: "x", acknowledge: false }));
    assert.equal(
      validateCloseAccountForm({ currentPassword: "secret", acknowledge: true }),
      null,
    );
  });

  it("maps wrong-password messaging", () => {
    assert.equal(
      mapCloseAccountError("Current password is incorrect."),
      "Current password is incorrect.",
    );
  });

  it("maps success messaging", () => {
    assert.match(mapCloseAccountSuccess(), /closed/i);
  });
});
