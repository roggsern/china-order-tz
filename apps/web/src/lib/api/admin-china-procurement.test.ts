import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  PROCUREMENT_SECTIONS,
  canManageChinaProcurement,
  canViewChinaProcurement,
} from "@/lib/api/admin-china-procurement";

describe("admin china procurement board", () => {
  it("gates board access with procurement permissions", () => {
    assert.equal(canViewChinaProcurement(["procurement.view"]), true);
    assert.equal(canViewChinaProcurement(["purchase_orders.view"]), false);
    assert.equal(canManageChinaProcurement(["procurement.update"]), true);
    assert.equal(canManageChinaProcurement(["procurement.view"]), false);
  });

  it("exposes procurement workflow sections", () => {
    assert.deepEqual(
      PROCUREMENT_SECTIONS.map((section) => section.status),
      ["pending", "purchasing", "purchased", "qc_pending", "completed"],
    );
  });
});
