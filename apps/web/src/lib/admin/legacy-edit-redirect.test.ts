import assert from "node:assert/strict";
import { test } from "node:test";
import { isLegacyEditRedirectEnabled } from "./legacy-edit-redirect";

test("isLegacyEditRedirectEnabled defaults ON for canonical-first workflow", () => {
  assert.equal(isLegacyEditRedirectEnabled(undefined), true);
  assert.equal(isLegacyEditRedirectEnabled(""), true);
  assert.equal(isLegacyEditRedirectEnabled("1"), true);
  assert.equal(isLegacyEditRedirectEnabled("true"), true);
  assert.equal(isLegacyEditRedirectEnabled("yes"), true);
});

test("isLegacyEditRedirectEnabled opt-out disables numeric-route redirect", () => {
  assert.equal(isLegacyEditRedirectEnabled("0"), false);
  assert.equal(isLegacyEditRedirectEnabled("false"), false);
  assert.equal(isLegacyEditRedirectEnabled("no"), false);
});
