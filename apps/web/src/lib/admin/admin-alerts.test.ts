import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  filterAdminAlerts,
  adminAlertsEmptyMessage,
  formatAdminAlertSource,
} from "@/lib/admin/admin-alerts";
import type { AdminAlert } from "@/lib/api/admin-reporting";

const sampleAlerts: AdminAlert[] = [
  {
    severity: "HIGH",
    title: "Stuck fulfilment",
    message: "3 item(s) need attention.",
    source: "operational",
    created_at: "2026-07-28T10:00:00+00:00",
    href: "/admin/fulfillments",
  },
  {
    severity: "MEDIUM",
    title: "Traffic is up but conversion is down",
    message: "Review checkout friction.",
    source: "growth",
    created_at: "2026-07-28T10:00:00+00:00",
  },
];

describe("admin-alerts", () => {
  it("formats alert source labels", () => {
    assert.equal(formatAdminAlertSource("operational"), "Operational");
    assert.equal(formatAdminAlertSource("growth"), "Growth");
  });

  it("filters alerts by source", () => {
    assert.equal(filterAdminAlerts(sampleAlerts, "all").length, 2);
    assert.equal(filterAdminAlerts(sampleAlerts, "operational").length, 1);
    assert.equal(filterAdminAlerts(sampleAlerts, "growth")[0]?.title, sampleAlerts[1]?.title);
  });

  it("returns contextual empty messages", () => {
    assert.match(adminAlertsEmptyMessage("all"), /clear/i);
    assert.match(adminAlertsEmptyMessage("operational"), /operational/i);
    assert.match(adminAlertsEmptyMessage("growth"), /growth/i);
  });
});
