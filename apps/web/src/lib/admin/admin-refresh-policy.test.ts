import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  buildQueueRefreshSnapshot,
  preserveSelectionAfterRefresh,
  runDedupedAdminRefresh,
  shouldSkipAutoRefreshTick,
} from "@/lib/admin/admin-auto-refresh";
import {
  ADMIN_PAGE_REFRESH_POLICY,
  formatAdminRefreshPolicyLabel,
  getAdminRefreshPolicy,
  isAdminAutoRefreshEnabled,
  resolveAdminRefreshIntervalMs,
  usesExternalOrdersRealtime,
} from "@/lib/admin/admin-refresh-policy";

describe("admin-refresh-policy", () => {
  it("maps operational pages to HIGH_ACTIVITY intervals", () => {
    assert.equal(ADMIN_PAGE_REFRESH_POLICY.fulfillment_queue.visibleMs, 15_000);
    assert.equal(ADMIN_PAGE_REFRESH_POLICY.fulfillment_queue.hiddenMs, 30_000);
    assert.equal(ADMIN_PAGE_REFRESH_POLICY.warehouse_queue.activity, "HIGH_ACTIVITY");
    assert.equal(ADMIN_PAGE_REFRESH_POLICY.orders_queue.activity, "HIGH_ACTIVITY");
  });

  it("maps command center, alerts, and shipments to MEDIUM_ACTIVITY intervals", () => {
    assert.equal(resolveAdminRefreshIntervalMs("command_center", false), 30_000);
    assert.equal(resolveAdminRefreshIntervalMs("command_center", true), 60_000);
    assert.equal(resolveAdminRefreshIntervalMs("alerts", false), 30_000);
    assert.equal(getAdminRefreshPolicy("shipments").activity, "MEDIUM_ACTIVITY");
    assert.equal(getAdminRefreshPolicy("catalog_health").activity, "MEDIUM_ACTIVITY");
  });

  it("disables polling for LOW_ACTIVITY pages", () => {
    assert.equal(isAdminAutoRefreshEnabled("products"), false);
    assert.equal(isAdminAutoRefreshEnabled("settings"), false);
    assert.equal(isAdminAutoRefreshEnabled("reports"), false);
    assert.equal(resolveAdminRefreshIntervalMs("reports", false), null);
  });

  it("documents orders queue external realtime transport", () => {
    assert.equal(usesExternalOrdersRealtime("orders_queue"), true);
    assert.equal(usesExternalOrdersRealtime("fulfillment_queue"), false);
    assert.match(formatAdminRefreshPolicyLabel("command_center"), /Medium activity/);
  });
});

describe("admin-auto-refresh helpers", () => {
  it("skips ticks when disabled, in flight, or polling disabled", () => {
    assert.equal(
      shouldSkipAutoRefreshTick({
        enabled: false,
        inFlight: false,
        page: "fulfillment_queue",
        hidden: false,
      }),
      true,
    );
    assert.equal(
      shouldSkipAutoRefreshTick({
        enabled: true,
        inFlight: true,
        page: "fulfillment_queue",
        hidden: false,
      }),
      true,
    );
    assert.equal(
      shouldSkipAutoRefreshTick({
        enabled: true,
        inFlight: false,
        page: "reports",
        hidden: false,
      }),
      true,
    );
  });

  it("deduplicates concurrent refresh runs", async () => {
    const state = { inFlight: false };
    let runs = 0;

    const first = runDedupedAdminRefresh(state, async () => {
      runs += 1;
      await new Promise((resolve) => setTimeout(resolve, 10));
    });
    const second = runDedupedAdminRefresh(state, async () => {
      runs += 1;
    });

    const [firstResult, secondResult] = await Promise.all([first, second]);
    assert.equal(firstResult, true);
    assert.equal(secondResult, false);
    assert.equal(runs, 1);
  });

  it("preserves queue filters and pagination in refresh snapshots", () => {
    const snapshot = buildQueueRefreshSnapshot({
      filters: { journey: "china", status: "processing" },
      page: 3,
      selectedIds: ["a", "b"],
    });

    assert.deepEqual(snapshot.filters, { journey: "china", status: "processing" });
    assert.equal(snapshot.page, 3);
    assert.deepEqual(snapshot.selectedIds, ["a", "b"]);
  });

  it("preserves bulk selections for rows still visible after refresh", () => {
    const next = preserveSelectionAfterRefresh(new Set(["keep", "drop"]), ["keep", "new"]);
    assert.deepEqual([...next], ["keep"]);
  });
});

describe("dashboard and queue refresh integration contracts", () => {
  it("uses medium interval for command center dashboard API refresh", () => {
    assert.equal(resolveAdminRefreshIntervalMs("command_center", false), 30_000);
    assert.equal(isAdminAutoRefreshEnabled("command_center"), true);
  });

  it("uses high interval for fulfilment and warehouse queues", () => {
    assert.equal(resolveAdminRefreshIntervalMs("fulfillment_queue", false), 15_000);
    assert.equal(resolveAdminRefreshIntervalMs("warehouse_queue", true), 30_000);
  });

  it("allows queue refresh to keep client-side filters separate from server filters", () => {
    const snapshot = buildQueueRefreshSnapshot({
      filters: { journey: "local", status: "all" },
      page: 2,
      selectedIds: ["ful-1"],
    });

    assert.equal(snapshot.filters.journey, "local");
    assert.equal(snapshot.page, 2);
    assert.equal(snapshot.selectedIds.length, 1);
  });
});
