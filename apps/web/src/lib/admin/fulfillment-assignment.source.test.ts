import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, it } from "node:test";

const webSrc = join(process.cwd(), "src");
const apiRoot = join(process.cwd(), "..", "api");

function readWeb(relativePath: string): string {
  return readFileSync(join(webSrc, relativePath), "utf8");
}

function readApi(relativePath: string): string {
  return readFileSync(join(apiRoot, relativePath), "utf8");
}

describe("fulfillment assignment wave 1 source contracts", () => {
  it("detail shows Assigned operator with assign, reassign, and unassign", () => {
    const workspace = readWeb("components/admin/AdminFulfillmentOperationalWorkspace.tsx");
    const control = readWeb("components/admin/AdminFulfillmentAssignmentControl.tsx");

    assert.ok(workspace.includes("AdminFulfillmentAssignmentControl"));
    assert.ok(workspace.includes('hasAdminPermission(permissions, "orders.fulfill")'));
    assert.ok(control.includes("Assigned operator"));
    assert.ok(control.includes("Unassigned"));
    assert.ok(control.includes("Select operator"));
    assert.ok(control.includes(">Assign<") || control.includes("Assign"));
    assert.ok(control.includes("Reassign"));
    assert.ok(control.includes("Unassign"));
    assert.ok(control.includes("fetchFulfillmentAssignees"));
    assert.ok(control.includes("updateAdminFulfillmentAssignment"));
    assert.ok(control.includes("savingRef"));
    assert.ok(control.includes("setCurrentAssignee(nextAssignee)"));
    assert.ok(control.includes("Unable to update assignment."));
    assert.doesNotMatch(control, /updateAdminFulfillmentStatus/);
    assert.doesNotMatch(control, /\/status/);
    assert.doesNotMatch(control, /Claim/);
  });

  it("assignment client and BFF use the assignment endpoint", () => {
    const client = readWeb("lib/api/admin-fulfillments.ts");
    const assignmentRoute = readWeb(
      "app/api/admin/fulfillments/[fulfillment]/assignment/route.ts",
    );
    const assigneesRoute = readWeb("app/api/admin/fulfillments/assignees/route.ts");

    assert.ok(client.includes("fetchFulfillmentAssignees"));
    assert.ok(client.includes("/api/admin/fulfillments/assignees"));
    assert.ok(client.includes("updateAdminFulfillmentAssignment"));
    assert.ok(client.includes("/assignment"));
    assert.match(
      client,
      /updateAdminFulfillmentAssignment[\s\S]*\/assignment/,
    );
    assert.ok(assignmentRoute.includes("/fulfillments/${encodeURIComponent(fulfillment)}/assignment"));
    assert.ok(assigneesRoute.includes("/fulfillments/assignees"));
  });

  it("queue remains customer-first without an Assigned heading", () => {
    const panel = readWeb("components/admin/AdminFulfillmentQueuePanel.tsx");
    const orderCard = readWeb("components/admin/AdminOrderFulfillmentCard.tsx");

    assert.ok(panel.includes(">Customer<"));
    assert.doesNotMatch(panel, />Assigned</);
    assert.doesNotMatch(panel, /AdminFulfillmentAssignmentControl/);
    assert.ok(orderCard.includes("Assigned"));
    assert.doesNotMatch(orderCard, /updateAdminFulfillmentAssignment/);
  });

  it("API assignment route and audit stay independent of status history", () => {
    const routes = readApi("routes/api.php");
    const engine = readApi("app/Services/Fulfillment/FulfillmentEngine.php");
    const request = readApi("app/Http/Requests/Admin/UpdateFulfillmentAssignmentRequest.php");
    const audit = readApi("app/Events/Audit/FulfillmentAssignedAudit.php");
    const statusRequest = readApi("app/Http/Requests/Admin/UpdateFulfillmentStatusRequest.php");

    assert.ok(routes.includes("/fulfillments/assignees"));
    assert.ok(routes.includes("/fulfillments/{fulfillment}/assignment"));
    assert.ok(engine.includes("function assign("));
    assert.ok(engine.includes("lockForUpdate()"));
    assert.ok(engine.includes("FulfillmentAssignedAudit"));
    assert.ok(request.includes("ORDERS_FULFILL"));
    assert.ok(audit.includes("ActivityEventType::FulfillmentAssigned"));
    assert.ok(statusRequest.includes("'assigned_to'"));
    const assignMethod = engine.slice(engine.indexOf("public function assign("));
    assert.doesNotMatch(assignMethod, /historyRecorder/);
    assert.ok(assignMethod.includes("FulfillmentAssignedAudit"));
  });
});
