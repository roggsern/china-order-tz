import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "path";
import { describe, it } from "node:test";

const webSrc = join(process.cwd(), "src");
const apiRoot = join(process.cwd(), "..", "api");

function readWeb(relativePath: string): string {
  return readFileSync(join(webSrc, relativePath), "utf8");
}

function readApi(relativePath: string): string {
  return readFileSync(join(apiRoot, relativePath), "utf8");
}

describe("fulfillment bulk assignment wave 1 source contracts", () => {
  it("queue keeps Customer and does not restore Assigned as a heading", () => {
    const panel = readWeb("components/admin/AdminFulfillmentQueuePanel.tsx");

    assert.ok(panel.includes(">Customer<"));
    assert.ok(panel.includes(">Order<"));
    assert.ok(panel.includes(">Product<"));
    assert.ok(panel.includes(">Journey<"));
    assert.ok(panel.includes(">Stage<"));
    assert.ok(panel.includes(">Age<"));
    assert.ok(panel.includes(">Required action<"));
    assert.ok(panel.includes(">Action<"));
    assert.doesNotMatch(panel, />Assigned</);
  });

  it("shows a desktop-only assignment bar for selected fulfillable rows", () => {
    const panel = readWeb("components/admin/AdminFulfillmentQueuePanel.tsx");
    const bar = readWeb("components/admin/AdminFulfillmentBulkAssignmentBar.tsx");

    assert.ok(panel.includes("AdminFulfillmentBulkAssignmentBar"));
    assert.ok(panel.includes('hasAdminPermission(permissions, "orders.fulfill")'));
    assert.ok(bar.includes("hidden"));
    assert.ok(bar.includes("lg:block"));
    assert.ok(bar.includes("selected on this page"));
    assert.ok(bar.includes("Select operator"));
    assert.ok(bar.includes("Assign"));
    assert.ok(bar.includes("Unassign"));
    assert.ok(bar.includes("fetchFulfillmentAssignees"));
    assert.ok(bar.includes("updateAdminFulfillmentBulkAssignment"));
    assert.ok(bar.includes("savingRef"));
    assert.ok(bar.includes("buildBulkAssignmentPayload"));
    assert.doesNotMatch(bar, /Claim/);
    assert.doesNotMatch(bar, /\/status/);
    assert.doesNotMatch(bar, /bulk-action/);
    assert.doesNotMatch(bar, /confirm/i);
  });

  it("prunes hidden selections and submits visible ids only", () => {
    const panel = readWeb("components/admin/AdminFulfillmentQueuePanel.tsx");
    const helpers = readWeb("lib/admin/fulfillment-bulk-assignment.ts");

    assert.ok(panel.includes("pruneSelectionToVisible"));
    assert.ok(panel.includes("resolveVisibleSelectedIds"));
    assert.ok(panel.includes("toggleSelectAllVisible"));
    assert.ok(panel.includes("clearTableSelection"));
    assert.ok(panel.includes("[journeyFilter, statusFilter]"));
    assert.ok(panel.includes("preserveSelectionAfterRefresh"));
    assert.ok(helpers.includes("resolveVisibleSelectedIds"));
    assert.ok(helpers.includes("assigned_to"));
  });

  it("keeps lifecycle bulk actions on a separate bar and API", () => {
    const panel = readWeb("components/admin/AdminFulfillmentQueuePanel.tsx");
    const lifecycleBar = readWeb("components/admin/AdminFulfillmentBulkActionBar.tsx");
    const lifecycleClient = readWeb("lib/api/admin-fulfillment-bulk.ts");
    const assignmentClient = readWeb("lib/api/admin-fulfillment-bulk-assignment.ts");
    const assignmentBff = readWeb("app/api/admin/fulfillments/bulk-assignment/route.ts");

    assert.ok(panel.includes("AdminFulfillmentBulkActionBar"));
    assert.doesNotMatch(lifecycleBar, /bulk-assignment/);
    assert.doesNotMatch(lifecycleBar, /Assign/);
    assert.ok(lifecycleClient.includes("/api/admin/fulfillments/bulk-action"));
    assert.ok(assignmentClient.includes("/api/admin/fulfillments/bulk-assignment"));
    assert.doesNotMatch(assignmentClient, /\/status/);
    assert.doesNotMatch(assignmentClient, /bulk-action/);
    assert.ok(assignmentBff.includes("/fulfillments/bulk-assignment"));
    assert.doesNotMatch(assignmentBff, /\/status/);
    assert.doesNotMatch(assignmentBff, /bulk-action/);
  });

  it("API bulk-assignment is static, atomic, and reuses assign()", () => {
    const routes = readApi("routes/api.php");
    const request = readApi("app/Http/Requests/Admin/UpdateFulfillmentBulkAssignmentRequest.php");
    const service = readApi("app/Services/Fulfillment/FulfillmentBulkAssignmentService.php");
    const controller = readApi("app/Http/Controllers/Admin/AdminFulfillmentController.php");
    const lifecycle = readApi("app/Services/Fulfillment/FulfillmentBulkActionService.php");

    assert.ok(routes.includes("/fulfillments/bulk-assignment"));
    const bulkAssignmentAt = routes.indexOf("/fulfillments/bulk-assignment");
    const fulfillmentParamAt = routes.indexOf("/fulfillments/{fulfillment}");
    assert.ok(bulkAssignmentAt > -1 && fulfillmentParamAt > bulkAssignmentAt);

    assert.ok(request.includes("ORDERS_FULFILL"));
    assert.ok(request.includes("EligibleFulfillmentAssignee"));
    assert.ok(request.includes("max:'.self::MAX_BATCH_SIZE"));
    assert.doesNotMatch(request, /exists:fulfillments,id/);

    assert.ok(service.includes("function assign("));
    assert.ok(service.includes("engine->assign("));
    assert.ok(service.includes("DB::transaction"));
    assert.ok(service.includes("One or more selected fulfillments do not exist."));
    assert.doesNotMatch(service, /updateStatus\(/);
    assert.doesNotMatch(service, /assignMany/);
    assert.doesNotMatch(service, /FulfillmentBulkActionCompleted/);
    assert.doesNotMatch(lifecycle, /bulk-assignment/);
    assert.doesNotMatch(lifecycle, /BulkAssignment/);

    assert.ok(controller.includes("function bulkAssignment("));
    assert.ok(service.includes("'requested'"));
    assert.ok(service.includes("'changed'"));
    assert.ok(service.includes("'unchanged'"));
  });
});
