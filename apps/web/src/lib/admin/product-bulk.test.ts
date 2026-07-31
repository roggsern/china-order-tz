import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  PRODUCT_BULK_ACTIONS,
  buildProductBulkConfirmationMessage,
  groupProductBulkFailures,
  resolveVisibleProductBulkActions,
  summarizeProductBulkResults,
  validateProductBulkPayload,
  type ProductBulkActionDefinition,
  type ProductBulkActionResponse,
} from "@/lib/admin/product-bulk";
import {
  clearTableSelection,
  createEmptySelection,
  resolveTableSelectionState,
  toggleSelectAllVisible,
  toggleTableSelection,
} from "@/lib/admin/table-selection";

function actionByKey(key: string): ProductBulkActionDefinition {
  const action = PRODUCT_BULK_ACTIONS.find((item) => item.key === key);
  assert.ok(action, `missing action ${key}`);
  return action;
}

describe("product bulk selection", () => {
  it("toggles row selection and select-all for visible ids", () => {
    let selected = createEmptySelection<string>();
    selected = toggleTableSelection(selected, "p1");
    selected = toggleTableSelection(selected, "p2");
    assert.equal(selected.size, 2);

    selected = toggleSelectAllVisible(selected, ["p1", "p2", "p3"]);
    assert.deepEqual([...selected].sort(), ["p1", "p2", "p3"]);

    selected = toggleSelectAllVisible(selected, ["p1", "p2", "p3"]);
    assert.equal(selected.size, 0);

    selected = clearTableSelection();
    assert.equal(selected.size, 0);
  });

  it("reports all-visible selected state", () => {
    const selected = new Set(["a", "b"]);
    const state = resolveTableSelectionState(selected, ["a", "b"]);
    assert.equal(state.allVisibleSelected, true);
    assert.equal(state.selectedCount, 2);
  });
});

describe("product bulk actions visibility", () => {
  it("shows all actions when permissions are undefined", () => {
    assert.equal(resolveVisibleProductBulkActions(undefined).length, PRODUCT_BULK_ACTIONS.length);
  });

  it("filters by catalog, pricing, and inventory permissions", () => {
    const visible = resolveVisibleProductBulkActions([
      "catalog.publish",
      "pricing.manage",
      "inventory.adjust",
    ]);
    assert.deepEqual(
      visible.map((action) => action.key),
      [
        "publish",
        "pricing_percentage_increase",
        "pricing_percentage_decrease",
        "pricing_fixed",
        "inventory_increase",
        "inventory_decrease",
        "inventory_set",
      ],
    );
  });

  it("allows archive with catalog.archive or catalog.update", () => {
    assert.ok(
      resolveVisibleProductBulkActions(["catalog.archive"]).some(
        (action) => action.key === "archive",
      ),
    );
    assert.ok(
      resolveVisibleProductBulkActions(["catalog.update"]).some(
        (action) => action.key === "archive",
      ),
    );
  });
});

describe("product bulk confirmation and validation", () => {
  it("builds confirmation messages for status, pricing, and inventory", () => {
    assert.match(
      buildProductBulkConfirmationMessage(actionByKey("publish"), 2, {}),
      /publish 2 selected products/,
    );
    assert.match(
      buildProductBulkConfirmationMessage(actionByKey("pricing_percentage_increase"), 1, {
        percent: 10,
      }),
      /Percent: 10%/,
    );
    assert.match(
      buildProductBulkConfirmationMessage(actionByKey("pricing_fixed"), 3, { amount: 1500 }),
      /Amount: 1500/,
    );
    assert.match(
      buildProductBulkConfirmationMessage(actionByKey("inventory_set"), 1, { quantity: 8 }),
      /Quantity: 8/,
    );
  });

  it("validates percent, amount, and quantity payloads", () => {
    assert.equal(
      validateProductBulkPayload(actionByKey("pricing_percentage_decrease"), { percent: 5 }),
      null,
    );
    assert.match(
      validateProductBulkPayload(actionByKey("pricing_percentage_decrease"), { percent: 0 }) ?? "",
      /positive percent/,
    );
    assert.match(
      validateProductBulkPayload(actionByKey("pricing_fixed"), { amount: -1 }) ?? "",
      /non-negative amount/,
    );
    assert.match(
      validateProductBulkPayload(actionByKey("inventory_increase"), { quantity: 0 }) ?? "",
      /greater than zero/,
    );
    assert.equal(
      validateProductBulkPayload(actionByKey("inventory_set"), { quantity: 0 }),
      null,
    );
  });
});

describe("product bulk results", () => {
  it("summarizes and groups per-product failures", () => {
    const result: ProductBulkActionResponse = {
      batch_id: "batch-1",
      action_key: "publish",
      total: 3,
      succeeded: 1,
      failed: 2,
      results: [
        { product_id: "1", success: true, message: "ok" },
        { product_id: "2", success: false, message: "Not publishable" },
        { product_id: "3", success: false, message: "Not publishable" },
      ],
    };

    assert.equal(summarizeProductBulkResults(result), "1 succeeded, 2 failed of 3.");
    assert.deepEqual(groupProductBulkFailures(result.results), [
      { message: "Not publishable", count: 2 },
    ]);
  });
});
