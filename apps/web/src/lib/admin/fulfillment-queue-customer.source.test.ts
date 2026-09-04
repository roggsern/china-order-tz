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

describe("fulfillment queue customer column source contracts", () => {
  it("desktop table shows Customer instead of Assigned", () => {
    const panel = readWeb("components/admin/AdminFulfillmentQueuePanel.tsx");

    assert.ok(panel.includes(">Customer<"));
    assert.ok(panel.includes("row.customerName"));
    assert.ok(panel.includes("row.customerPhone"));
    assert.doesNotMatch(panel, />Assigned</);
    assert.doesNotMatch(panel, /row\.assignedLabel/);
    assert.ok(panel.includes(">Order<"));
    assert.ok(panel.includes(">Product<"));
    assert.ok(panel.includes(">Journey<"));
    assert.ok(panel.includes(">Stage<"));
    assert.ok(panel.includes(">Age<"));
    assert.ok(panel.includes(">Required action<"));
    assert.ok(panel.includes(">Action<"));
    assert.match(panel, />\s*Open\s*</);
    assert.doesNotMatch(panel, /fetchAdminCustomer/);
  });

  it("keeps assignment internals and detail displays", () => {
    const mapper = readWeb("lib/admin/fulfillment-operational.ts");
    const engine = readApi("app/Services/Fulfillment/FulfillmentEngine.php");
    const request = readApi("app/Http/Requests/Admin/UpdateFulfillmentStatusRequest.php");
    const detail = readWeb("components/admin/AdminFulfillmentAssignmentControl.tsx");
    const orderCard = readWeb("components/admin/AdminOrderFulfillmentCard.tsx");
    const index = readApi("app/Http/Controllers/Admin/AdminFulfillmentController.php");
    const resource = readApi("app/Http/Resources/FulfillmentResource.php");

    assert.ok(mapper.includes("assignedLabel"));
    assert.ok(mapper.includes("assigned_to"));
    assert.ok(engine.includes("'assigned_to'"));
    assert.ok(request.includes("'assigned_to'"));
    assert.ok(detail.includes("Unassigned"));
    assert.ok(orderCard.includes("Assigned"));
    assert.ok(index.includes("'order.user'"));
    assert.ok(index.includes("'assignee'"));
    assert.ok(resource.includes("'phone' => $this->order->user->phone"));
    assert.doesNotMatch(mapper, /fetchAdminCustomer/);
  });
});
