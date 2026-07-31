import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { canViewChinaAnalytics } from "@/lib/api/admin-china-analytics";
import { hasAdminPermission } from "@/lib/api/admin-me";

describe("admin china analytics helpers", () => {
  it("requires analytics.view permission", () => {
    assert.equal(canViewChinaAnalytics(undefined), true);
    assert.equal(canViewChinaAnalytics(["analytics.view"]), true);
    assert.equal(canViewChinaAnalytics(["profit_reports.view"]), false);
    assert.equal(hasAdminPermission(["analytics.view"], "analytics.view"), true);
  });
});
