import assert from "node:assert/strict";
import { test } from "node:test";
import { isLegacyEditRedirectEnabled } from "./legacy-edit-redirect";

test("isLegacyEditRedirectEnabled defaults OFF and accepts common truthy values", () => {
  assert.equal(isLegacyEditRedirectEnabled(undefined), false);
  assert.equal(isLegacyEditRedirectEnabled("0"), false);
  assert.equal(isLegacyEditRedirectEnabled("false"), false);
  assert.equal(isLegacyEditRedirectEnabled("1"), true);
  assert.equal(isLegacyEditRedirectEnabled("true"), true);
  assert.equal(isLegacyEditRedirectEnabled("yes"), true);
});
