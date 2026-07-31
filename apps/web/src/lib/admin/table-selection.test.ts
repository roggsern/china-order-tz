import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  clearTableSelection,
  pruneSelectionToVisible,
  resolveTableSelectionState,
  toggleSelectAllVisible,
  toggleTableSelection,
} from "@/lib/admin/table-selection";

describe("table selection helpers", () => {
  it("toggles individual row selection", () => {
    let selected = clearTableSelection<string>();
    selected = toggleTableSelection(selected, "ff-1");
    assert.deepEqual([...selected], ["ff-1"]);
    selected = toggleTableSelection(selected, "ff-1");
    assert.equal(selected.size, 0);
  });

  it("selects and clears all visible rows", () => {
    const visible = ["ff-1", "ff-2"];
    let selected = clearTableSelection<string>();
    selected = toggleSelectAllVisible(selected, visible);
    assert.deepEqual([...selected].sort(), ["ff-1", "ff-2"]);

    selected = toggleSelectAllVisible(selected, visible);
    assert.equal(selected.size, 0);
  });

  it("resolves selection state for the current page", () => {
    const selected = new Set(["ff-1", "ff-3"]);
    const state = resolveTableSelectionState(selected, ["ff-1", "ff-2"]);
    assert.equal(state.selectedCount, 2);
    assert.equal(state.allVisibleSelected, false);
  });

  it("prunes selection to rows still visible on the page", () => {
    const selected = new Set(["ff-1", "ff-2", "ff-3"]);
    const pruned = pruneSelectionToVisible(selected, ["ff-2", "ff-4"]);
    assert.deepEqual([...pruned], ["ff-2"]);
  });
});
