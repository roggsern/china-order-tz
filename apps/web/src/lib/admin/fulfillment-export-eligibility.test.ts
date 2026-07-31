import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  isEligibleForMarkExportReady,
  MARK_EXPORT_READY_WORKFLOW_STAGES,
} from "@/lib/admin/fulfillment-export-eligibility";

describe("fulfillment export eligibility parity", () => {
  const eligible = {
    strategy: "china",
    status: "processing",
    delivery_type: "company_shipping",
    warehouse_status: "ready_to_ship",
    china: {
      stage: "qc_passed",
      qc_status: "passed",
      export_ready: false,
    },
  };

  it("requires company shipping, export-ready workflow stage, QC passed, and ready_to_ship warehouse", () => {
    assert.equal(isEligibleForMarkExportReady(eligible), true);
    assert.equal(
      isEligibleForMarkExportReady({ ...eligible, warehouse_status: "packed" }),
      false,
    );
    assert.equal(
      isEligibleForMarkExportReady({ ...eligible, delivery_type: "customer_agent" }),
      false,
    );
    assert.equal(
      isEligibleForMarkExportReady({
        ...eligible,
        china: { ...eligible.china, export_ready: true },
      }),
      false,
    );
  });

  it("documents export-ready workflow stages shared with single-order resolver", () => {
    assert.equal(MARK_EXPORT_READY_WORKFLOW_STAGES.has("qc_passed"), true);
    assert.equal(MARK_EXPORT_READY_WORKFLOW_STAGES.has("received"), true);
  });
});
