import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  buildBulkAssignmentPayload,
  buildBulkAssignmentSuccessMessage,
} from "@/lib/admin/fulfillment-bulk-assignment";
import {
  clearTableSelection,
  pruneSelectionToVisible,
  toggleSelectAllVisible,
  toggleTableSelection,
} from "@/lib/admin/table-selection";

describe("fulfillment bulk assignment helpers", () => {
  it("selects, deselects, and counts visible rows on the current page", () => {
    const visible = ["ff-1", "ff-2", "ff-3"];
    let selected = clearTableSelection<string>();
    selected = toggleTableSelection(selected, "ff-1");
    assert.equal(selected.size, 1);
    selected = toggleSelectAllVisible(selected, visible);
    assert.deepEqual([...selected].sort(), ["ff-1", "ff-2", "ff-3"]);
    selected = toggleTableSelection(selected, "ff-2");
    assert.equal(selected.size, 2);
    selected = toggleSelectAllVisible(selected, visible);
    assert.deepEqual([...selected].sort(), ["ff-1", "ff-2", "ff-3"]);
    selected = toggleSelectAllVisible(selected, visible);
    assert.equal(selected.size, 0);
  });

  it("builds assign and unassign payloads from visible ids only", () => {
    const selected = new Set(["ff-1", "ff-hidden", "ff-2"]);
    const visible = ["ff-1", "ff-2"];

    assert.deepEqual(buildBulkAssignmentPayload(selected, visible, "adm-1"), {
      fulfillment_ids: ["ff-1", "ff-2"],
      assigned_to: "adm-1",
    });
    assert.deepEqual(buildBulkAssignmentPayload(selected, visible, null), {
      fulfillment_ids: ["ff-1", "ff-2"],
      assigned_to: null,
    });
  });

  it("prunes hidden selections when search, action, or summary visibility changes", () => {
    const selected = new Set(["ff-1", "ff-2", "ff-3"]);
    const afterSearch = pruneSelectionToVisible(selected, ["ff-1"]);
    assert.deepEqual([...afterSearch], ["ff-1"]);

    const afterAction = pruneSelectionToVisible(selected, ["ff-2", "ff-3"]);
    assert.deepEqual([...afterAction].sort(), ["ff-2", "ff-3"]);

    const afterSummary = pruneSelectionToVisible(selected, ["ff-3"]);
    assert.deepEqual([...afterSummary], ["ff-3"]);
  });

  it("does not carry page or poll selections onto rows that left the visible set", () => {
    const selected = new Set(["page-1", "gone"]);
    const nextPage = pruneSelectionToVisible(selected, ["page-2"]);
    assert.equal(nextPage.size, 0);

    const afterPoll = pruneSelectionToVisible(new Set(["keep", "hidden", "dropped"]), [
      "keep",
    ]);
    assert.deepEqual([...afterPoll], ["keep"]);
  });

  it("builds short success copy for assign, reassign, and unassign", () => {
    assert.equal(
      buildBulkAssignmentSuccessMessage({
        requested: 8,
        assignedTo: "adm-1",
        assigneeName: "Amina",
      }),
      "Assigned 8 fulfillments to Amina",
    );
    assert.equal(
      buildBulkAssignmentSuccessMessage({
        requested: 5,
        assignedTo: "adm-2",
        assigneeName: "Jackson",
        hadExistingAssignee: true,
      }),
      "Reassigned 5 fulfillments to Jackson",
    );
    assert.equal(
      buildBulkAssignmentSuccessMessage({
        requested: 4,
        assignedTo: null,
      }),
      "Unassigned 4 fulfillments",
    );
  });
});
