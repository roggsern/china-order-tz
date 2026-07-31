import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  DEFAULT_MAINTENANCE_MESSAGE,
  isMaintenanceBlockedResponse,
  mapMaintenanceMessage,
  mapMaintenanceStatus,
} from "@/lib/storefront/maintenance";

describe("storefront maintenance mapping", () => {
  it("maps configured message for the maintenance page", () => {
    assert.equal(
      mapMaintenanceMessage("  Scheduled downtime  "),
      "Scheduled downtime",
    );
    assert.equal(mapMaintenanceMessage(""), DEFAULT_MAINTENANCE_MESSAGE);
    assert.equal(mapMaintenanceMessage(null), DEFAULT_MAINTENANCE_MESSAGE);
  });

  it("maps status payloads without leaking internals", () => {
    assert.deepEqual(
      mapMaintenanceStatus({ maintenance: true, message: "Back soon" }),
      { maintenance: true, message: "Back soon" },
    );
    assert.deepEqual(mapMaintenanceStatus({ maintenance: false, message: "x" }), {
      maintenance: false,
      message: null,
    });
    assert.deepEqual(mapMaintenanceStatus(undefined), {
      maintenance: false,
      message: null,
    });
  });

  it("detects blocked API responses", () => {
    assert.equal(
      isMaintenanceBlockedResponse(503, {
        maintenance: true,
        code: "maintenance_mode",
        message: "Closed",
      }),
      true,
    );
    assert.equal(isMaintenanceBlockedResponse(503, { message: "nope" }), false);
    assert.equal(
      isMaintenanceBlockedResponse(200, { maintenance: true }),
      false,
    );
  });
});
