import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  buildBulkCreateSupplierPurchaseConfirmationMessage,
  buildBulkLocalCompletedConfirmationMessage,
  buildBulkLocalReadyConfirmationMessage,
  buildBulkMarkChinaPackingCompleteConfirmationMessage,
  buildBulkMarkExportReadyConfirmationMessage,
  buildBulkMarkAgentDeliveredConfirmationMessage,
  buildBulkMarkCustomerCollectedConfirmationMessage,
  buildBulkMarkCustomerDeliveredConfirmationMessage,
  buildBulkCreateShipmentConfirmationMessage,
  buildBulkMarkQcPassedConfirmationMessage,
  buildBulkReceiveGoodsConfirmationMessage,
  buildLargeBulkSelectionWarning,
  canBulkChinaAction,
  countEligibleForBulkCreateSupplierPurchase,
  countEligibleForBulkLocalCompleted,
  countEligibleForBulkLocalReady,
  countEligibleForBulkMarkChinaPackingComplete,
  countEligibleForBulkMarkExportReady,
  countEligibleForBulkMarkAgentDelivered,
  countEligibleForBulkMarkCustomerCollected,
  countEligibleForBulkMarkCustomerDelivered,
  countEligibleForBulkCreateShipment,
  countEligibleForBulkMarkQcPassed,
  countEligibleForBulkReceiveGoods,
  createBulkOperationDraft,
  FULFILLMENT_BULK_ACTIONS,
  groupBulkResultReasons,
  hasBulkActionPermissions,
  hasSupplierPurchase,
  isChinaExportReady,
  isChinaImportFulfillment,
  isChinaQcPassed,
  isEligibleForBulkCreateSupplierPurchase,
  isEligibleForBulkLocalCompleted,
  isEligibleForBulkLocalReady,
  isEligibleForBulkMarkChinaPackingComplete,
  isEligibleForBulkMarkExportReady,
  isEligibleForBulkMarkAgentDelivered,
  isEligibleForBulkMarkCustomerCollected,
  isEligibleForBulkMarkCustomerDelivered,
  isEligibleForBulkCreateShipment,
  isEligibleForBulkMarkQcPassed,
  hasExistingShipment,
  isEligibleForBulkReceiveGoods,
  isPurchaseReceivable,
  mapAdminFulfillmentToBulkSelectionContext,
  parseChinaBulkSummary,
  resolveBulkSuccessLabel,
  resolveVisibleBulkActions,
  resolveBulkResultReasonLabel,
  resolveVisibleBulkActionsForSelection,
  shouldClearBulkSelectionAfterSuccess,
  shouldShowBulkActionBar,
  shouldWarnLargeBulkSelection,
  summarizeBulkOperation,
} from "@/lib/admin/fulfillment-bulk";

describe("fulfillment bulk MARK_LOCAL_ORDER_READY", () => {
  const permissions = ["warehouse.jobs.update", "warehouse.jobs.complete"];
  const readyAction = FULFILLMENT_BULK_ACTIONS.find(
    (action) => action.key === "MARK_LOCAL_ORDER_READY",
  );

  it("is enabled for execution", () => {
    assert.ok(readyAction);
    assert.equal(readyAction?.execution_enabled, true);
    assert.equal(readyAction?.confirmation_title, "Prepare selected orders?");
  });

  it("allows only eligible TZ local delivery types", () => {
    assert.equal(
      isEligibleForBulkLocalReady({
        id: "ff-1",
        strategy: "local",
        status: "processing",
        delivery_type: "self_pickup",
      }),
      true,
    );
    assert.equal(
      isEligibleForBulkLocalReady({
        id: "ff-2",
        strategy: "local",
        status: "processing",
        delivery_type: "negotiated_delivery",
      }),
      true,
    );
    assert.equal(
      isEligibleForBulkLocalReady({
        id: "ff-3",
        strategy: "china",
        status: "processing",
        delivery_type: "company_shipping",
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkLocalReady({
        id: "ff-4",
        strategy: "local",
        status: "processing",
        delivery_type: "customer_agent",
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkLocalReady({
        id: "ff-5",
        strategy: "local",
        status: "processing",
        delivery_type: "company_shipping",
      }),
      false,
    );
  });

  it("shows bulk action only when selection includes eligible local orders", () => {
    const eligible = [
      {
        id: "ff-1",
        strategy: "local",
        status: "processing",
        delivery_type: "self_pickup",
      },
    ];
    const mixed = [
      ...eligible,
      {
        id: "ff-2",
        strategy: "china",
        status: "processing",
        delivery_type: "company_shipping",
      },
    ];

    assert.equal(
      resolveVisibleBulkActionsForSelection(permissions, eligible).length,
      1,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(permissions, mixed).length,
      1,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(permissions, [
        {
          id: "ff-2",
          strategy: "china",
          status: "processing",
          delivery_type: "company_shipping",
        },
      ]).length,
      0,
    );
    assert.equal(
      shouldShowBulkActionBar(2, permissions, mixed),
      true,
    );
    assert.equal(countEligibleForBulkLocalReady(mixed), 1);
  });

  it("requires both warehouse permissions", () => {
    assert.ok(readyAction);
    assert.equal(hasBulkActionPermissions(readyAction, ["warehouse.jobs.update"]), false);
    assert.equal(
      hasBulkActionPermissions(readyAction, ["warehouse.jobs.update", "warehouse.jobs.complete"]),
      true,
    );
  });

  it("builds confirmation copy with eligible count", () => {
    assert.match(buildBulkLocalReadyConfirmationMessage(3), /3 local orders/);
    assert.match(buildBulkLocalReadyConfirmationMessage(1), /1 local order as ready/);
  });

  it("creates bulk operation drafts and summaries for audit preparation", () => {
    const draft = createBulkOperationDraft({
      actionKey: "MARK_LOCAL_ORDER_READY",
      adminId: "admin-1",
      fulfillmentIds: ["ff-1", "ff-2"],
      batchId: "batch-test-1",
    });

    assert.equal(draft.batch_id, "batch-test-1");
    assert.equal(draft.requested_count, 2);

    const summary = summarizeBulkOperation(draft, {
      succeeded: 2,
      failed: 0,
      skipped: 0,
    });

    assert.equal(summary.succeeded_count, 2);
    assert.equal(summary.status, "completed");
  });
});

describe("fulfillment bulk MARK_LOCAL_ORDER_COMPLETED", () => {
  const permissions = ["orders.fulfill"];
  const completedAction = FULFILLMENT_BULK_ACTIONS.find(
    (action) => action.key === "MARK_LOCAL_ORDER_COMPLETED",
  );

  it("is enabled for execution", () => {
    assert.ok(completedAction);
    assert.equal(completedAction?.label, "Mark order completed");
    assert.equal(completedAction?.execution_enabled, true);
  });

  it("allows only eligible ready TZ local delivery types", () => {
    assert.equal(
      isEligibleForBulkLocalCompleted({
        id: "ff-1",
        strategy: "local",
        status: "ready_for_shipping",
        delivery_type: "self_pickup",
        warehouse_status: "ready_to_ship",
      }),
      true,
    );
    assert.equal(
      isEligibleForBulkLocalCompleted({
        id: "ff-2",
        strategy: "local",
        status: "ready_for_shipping",
        delivery_type: "negotiated_delivery",
        warehouse_status: "ready_to_ship",
      }),
      true,
    );
    assert.equal(
      isEligibleForBulkLocalCompleted({
        id: "ff-3",
        strategy: "local",
        status: "processing",
        delivery_type: "self_pickup",
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkLocalCompleted({
        id: "ff-4",
        strategy: "china",
        status: "ready_for_shipping",
        delivery_type: "company_shipping",
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkLocalCompleted({
        id: "ff-5",
        strategy: "local",
        status: "ready_for_shipping",
        delivery_type: "customer_agent",
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkLocalCompleted({
        id: "ff-6",
        strategy: "local",
        status: "delivered",
        delivery_type: "self_pickup",
      }),
      false,
    );
  });

  it("shows bulk completion only when selection includes eligible ready orders", () => {
    const eligible = [
      {
        id: "ff-1",
        strategy: "local",
        status: "ready_for_shipping",
        delivery_type: "self_pickup",
        warehouse_status: "ready_to_ship",
      },
    ];
    const mixed = [
      ...eligible,
      {
        id: "ff-2",
        strategy: "china",
        status: "ready_for_shipping",
        delivery_type: "company_shipping",
      },
    ];

    assert.equal(
      resolveVisibleBulkActionsForSelection(permissions, eligible).length,
      1,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(permissions, mixed).length,
      1,
    );
    assert.equal(countEligibleForBulkLocalCompleted(mixed), 1);
  });

  it("shows both bulk actions when mixed selection includes pending and ready local orders", () => {
    const mixed = [
      {
        id: "ff-1",
        strategy: "local",
        status: "processing",
        delivery_type: "self_pickup",
      },
      {
        id: "ff-2",
        strategy: "local",
        status: "ready_for_shipping",
        delivery_type: "self_pickup",
        warehouse_status: "ready_to_ship",
      },
    ];

    const actions = resolveVisibleBulkActionsForSelection(
      ["warehouse.jobs.update", "warehouse.jobs.complete", "orders.fulfill"],
      mixed,
    );

    assert.equal(actions.length, 2);
    assert.equal(countEligibleForBulkLocalReady(mixed), 1);
    assert.equal(countEligibleForBulkLocalCompleted(mixed), 1);
  });

  it("requires orders.fulfill permission", () => {
    assert.ok(completedAction);
    assert.equal(hasBulkActionPermissions(completedAction, ["warehouse.jobs.update"]), false);
    assert.equal(hasBulkActionPermissions(completedAction, ["orders.fulfill"]), true);
  });

  it("builds confirmation copy with eligible count", () => {
    assert.match(buildBulkLocalCompletedConfirmationMessage(2), /2 local orders/);
    assert.match(buildBulkLocalCompletedConfirmationMessage(1), /1 local order as completed/);
  });

  it("clears selection after successful bulk completion", () => {
    assert.equal(
      shouldClearBulkSelectionAfterSuccess({
        batch_id: "batch-1",
        action_key: "MARK_LOCAL_ORDER_COMPLETED",
        total: 2,
        succeeded: 2,
        failed: 0,
        skipped: 0,
        results: [],
      }),
      true,
    );
    assert.equal(
      shouldClearBulkSelectionAfterSuccess({
        batch_id: "batch-2",
        action_key: "MARK_LOCAL_ORDER_COMPLETED",
        total: 2,
        succeeded: 0,
        failed: 2,
        skipped: 0,
        results: [],
      }),
      false,
    );
  });
});

describe("China bulk foundation", () => {
  const chinaPermissions = [
    "procurement.create",
    "procurement.update",
    "purchase_orders.receive",
    "warehouse.jobs.update",
    "warehouse.jobs.complete",
    "orders.ship",
  ];

  const disabledChinaActionKeys = [
    "CONFIRM_SUPPLIER_PURCHASE",
  ] as const;

  it("parses china summary metadata for bulk selection", () => {
    assert.deepEqual(
      parseChinaBulkSummary({
        stage: "qc_pending",
        qc_status: "passed",
        export_ready: true,
        has_supplier_purchase: true,
        purchase_receivable: false,
        supplier_purchase_state: "established",
      }),
      {
        stage: "qc_pending",
        qc_status: "passed",
        export_ready: true,
        has_supplier_purchase: true,
        purchase_receivable: false,
        supplier_purchase_state: "established",
      },
    );

    const context = mapAdminFulfillmentToBulkSelectionContext({
      id: "ff-china-1",
      strategy: "china",
      status: "processing",
      warehouse_status: "packed",
      shipment_status: "pending",
      china: {
        stage: "qc_passed",
        qc_status: "passed",
        export_ready: false,
        has_supplier_purchase: true,
        supplier_purchase_state: "active",
      },
      order: {
        id: "ord-1",
        order_number: "ORD-1",
        delivery_type: "company_shipping",
      },
    });

    assert.equal(context.strategy, "china");
    assert.equal(context.china?.stage, "qc_passed");
    assert.equal(context.shipment_status, "pending");
  });

  it("evaluates china eligibility helpers", () => {
    const selection = {
      id: "ff-china-1",
      strategy: "china",
      status: "processing",
      delivery_type: "company_shipping",
      china: {
        stage: "awaiting_procurement",
        qc_status: "pending",
        export_ready: false,
        has_supplier_purchase: false,
        supplier_purchase_state: "none",
      },
    };

    assert.equal(isChinaImportFulfillment(selection), true);
    assert.equal(isChinaQcPassed(selection), false);
    assert.equal(isChinaExportReady(selection), false);
    assert.equal(hasSupplierPurchase(selection), false);
    assert.equal(
      canBulkChinaAction("CREATE_SUPPLIER_PURCHASE", selection, chinaPermissions),
      true,
    );
    assert.equal(
      canBulkChinaAction("CREATE_SUPPLIER_PURCHASE", {
        ...selection,
        china: { ...selection.china, has_supplier_purchase: true },
      }, chinaPermissions),
      false,
    );
  });

  it("shows create supplier purchase for eligible China orders only", () => {
    const eligible = [
      {
        id: "ff-china-1",
        strategy: "china",
        status: "processing",
        delivery_type: "company_shipping",
        china: {
          stage: "awaiting_procurement",
          has_supplier_purchase: false,
        },
      },
    ];
    const withPurchase = [
      {
        id: "ff-china-2",
        strategy: "china",
        status: "processing",
        delivery_type: "company_shipping",
        china: {
          stage: "procurement_in_progress",
          has_supplier_purchase: true,
        },
      },
    ];
    const local = [
      {
        id: "ff-local-1",
        strategy: "local",
        status: "processing",
        delivery_type: "self_pickup",
      },
    ];

    assert.equal(isEligibleForBulkCreateSupplierPurchase(eligible[0]!), true);
    assert.equal(isEligibleForBulkCreateSupplierPurchase(withPurchase[0]!), false);
    assert.equal(isEligibleForBulkCreateSupplierPurchase(local[0]!), false);
    assert.equal(
      resolveVisibleBulkActionsForSelection(["procurement.create"], eligible).length,
      1,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(["procurement.create"], withPurchase).length,
      0,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(["procurement.create"], local).length,
      0,
    );
  });

  it("builds create supplier purchase confirmation with skipped count", () => {
    assert.match(
      buildBulkCreateSupplierPurchaseConfirmationMessage(2, 1),
      /2 China orders/,
    );
    assert.match(
      buildBulkCreateSupplierPurchaseConfirmationMessage(2, 1),
      /1 selected order will be skipped/,
    );
    assert.equal(resolveBulkSuccessLabel("CREATE_SUPPLIER_PURCHASE"), "Created");
  });

  it("keeps disabled china bulk actions hidden", () => {
    for (const key of disabledChinaActionKeys) {
      const action = FULFILLMENT_BULK_ACTIONS.find((candidate) => candidate.key === key);
      assert.ok(action, `missing china bulk action ${key}`);
      assert.equal(action?.execution_enabled, false);
    }

    const createAction = FULFILLMENT_BULK_ACTIONS.find(
      (candidate) => candidate.key === "CREATE_SUPPLIER_PURCHASE",
    );
    assert.ok(createAction);
    assert.equal(createAction?.execution_enabled, true);
    assert.equal(
      resolveVisibleBulkActions(["procurement.create"]).some(
        (action) => action.key === "CREATE_SUPPLIER_PURCHASE",
      ),
      true,
    );

    const receiveAction = FULFILLMENT_BULK_ACTIONS.find(
      (candidate) => candidate.key === "RECEIVE_GOODS",
    );
    assert.ok(receiveAction);
    assert.equal(receiveAction?.execution_enabled, true);
    assert.equal(receiveAction?.confirmation_title, "Receive goods for selected orders?");
    assert.equal(
      resolveVisibleBulkActions(["purchase_orders.receive"]).some(
        (action) => action.key === "RECEIVE_GOODS",
      ),
      true,
    );

    const qcAction = FULFILLMENT_BULK_ACTIONS.find(
      (candidate) => candidate.key === "MARK_QC_PASSED",
    );
    assert.ok(qcAction);
    assert.equal(qcAction?.execution_enabled, true);
    assert.equal(qcAction?.confirmation_title, "Mark selected orders as QC passed?");
    assert.equal(
      resolveVisibleBulkActions(["procurement.update"]).some(
        (action) => action.key === "MARK_QC_PASSED",
      ),
      true,
    );

    const packingAction = FULFILLMENT_BULK_ACTIONS.find(
      (candidate) => candidate.key === "MARK_CHINA_PACKING_COMPLETE",
    );
    assert.ok(packingAction);
    assert.equal(packingAction?.execution_enabled, true);
    assert.equal(packingAction?.confirmation_title, "Complete packing for selected China orders?");
    assert.equal(
      resolveVisibleBulkActions(["warehouse.jobs.update", "warehouse.jobs.complete"]).some(
        (action) => action.key === "MARK_CHINA_PACKING_COMPLETE",
      ),
      true,
    );

    const exportAction = FULFILLMENT_BULK_ACTIONS.find(
      (candidate) => candidate.key === "MARK_EXPORT_READY",
    );
    assert.ok(exportAction);
    assert.equal(exportAction?.execution_enabled, true);
    assert.equal(exportAction?.confirmation_title, "Mark selected China shipments as export ready?");
    assert.equal(
      resolveVisibleBulkActions(["warehouse.jobs.complete"]).some(
        (action) => action.key === "MARK_EXPORT_READY",
      ),
      true,
    );
  });

  it("keeps TZ_LOCAL bulk actions unchanged", () => {
    const localPermissions = ["warehouse.jobs.update", "warehouse.jobs.complete", "orders.fulfill"];
    const visible = resolveVisibleBulkActions(localPermissions);

    assert.equal(visible.length, 6);
    assert.deepEqual(
      visible.map((action) => action.key),
      [
        "MARK_LOCAL_ORDER_READY",
        "MARK_LOCAL_ORDER_COMPLETED",
        "MARK_CHINA_PACKING_COMPLETE",
        "MARK_EXPORT_READY",
        "MARK_CUSTOMER_COLLECTED",
        "MARK_CUSTOMER_DELIVERED",
      ],
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(localPermissions, [
        {
          id: "ff-local-1",
          strategy: "local",
          status: "processing",
          delivery_type: "self_pickup",
        },
      ]).length,
      1,
    );
  });
});

describe("China bulk RECEIVE_GOODS", () => {
  const receivePermissions = ["purchase_orders.receive"];

  const receivableSelection = {
    id: "ff-china-receive-1",
    strategy: "china",
    status: "processing",
    delivery_type: "company_shipping",
    china: {
      stage: "procurement_in_progress",
      has_supplier_purchase: true,
      purchase_receivable: true,
    },
  };

  it("evaluates receive goods eligibility helpers", () => {
    assert.equal(isPurchaseReceivable(receivableSelection), true);
    assert.equal(isEligibleForBulkReceiveGoods(receivableSelection), true);
    assert.equal(
      canBulkChinaAction("RECEIVE_GOODS", receivableSelection, receivePermissions),
      true,
    );
    assert.equal(
      isEligibleForBulkReceiveGoods({
        ...receivableSelection,
        strategy: "local",
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkReceiveGoods({
        ...receivableSelection,
        china: { ...receivableSelection.china, has_supplier_purchase: false },
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkReceiveGoods({
        ...receivableSelection,
        china: { ...receivableSelection.china, purchase_receivable: false },
      }),
      false,
    );
  });

  it("shows receive goods only for eligible China orders", () => {
    const withoutPurchase = [
      {
        id: "ff-china-2",
        strategy: "china",
        status: "processing",
        delivery_type: "company_shipping",
        china: {
          stage: "procurement_in_progress",
          has_supplier_purchase: false,
          purchase_receivable: false,
        },
      },
    ];
    const local = [
      {
        id: "ff-local-1",
        strategy: "local",
        status: "processing",
        delivery_type: "self_pickup",
      },
    ];

    assert.equal(countEligibleForBulkReceiveGoods([receivableSelection]), 1);
    assert.equal(
      resolveVisibleBulkActionsForSelection(receivePermissions, [receivableSelection]).length,
      1,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(receivePermissions, withoutPurchase).length,
      0,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(receivePermissions, local).length,
      0,
    );
  });

  it("builds receive goods confirmation with skipped count", () => {
    assert.match(
      buildBulkReceiveGoodsConfirmationMessage(2, 1),
      /mark goods as received for 2 China orders/,
    );
    assert.match(
      buildBulkReceiveGoodsConfirmationMessage(2, 1),
      /1 selected order will be skipped/,
    );
    assert.equal(resolveBulkSuccessLabel("RECEIVE_GOODS"), "Received");
  });
});

describe("China bulk MARK_QC_PASSED", () => {
  const qcPermissions = ["procurement.update"];

  const eligibleSelection = {
    id: "ff-china-qc-1",
    strategy: "china",
    status: "processing",
    delivery_type: "company_shipping",
    china: {
      stage: "qc_pending",
      qc_status: "pending",
      has_supplier_purchase: true,
    },
  };

  it("evaluates mark QC passed eligibility helpers", () => {
    assert.equal(isEligibleForBulkMarkQcPassed(eligibleSelection), true);
    assert.equal(
      canBulkChinaAction("MARK_QC_PASSED", eligibleSelection, qcPermissions),
      true,
    );
    assert.equal(
      isEligibleForBulkMarkQcPassed({
        ...eligibleSelection,
        strategy: "local",
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkMarkQcPassed({
        ...eligibleSelection,
        china: { ...eligibleSelection.china, qc_status: "passed" },
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkMarkQcPassed({
        ...eligibleSelection,
        china: { ...eligibleSelection.china, stage: "procurement_in_progress" },
      }),
      false,
    );
  });

  it("shows mark QC passed only for eligible China orders", () => {
    const alreadyPassed = [
      {
        id: "ff-china-qc-2",
        strategy: "china",
        status: "processing",
        delivery_type: "company_shipping",
        china: {
          stage: "qc_passed",
          qc_status: "passed",
        },
      },
    ];
    const local = [
      {
        id: "ff-local-1",
        strategy: "local",
        status: "processing",
        delivery_type: "self_pickup",
      },
    ];

    assert.equal(countEligibleForBulkMarkQcPassed([eligibleSelection]), 1);
    assert.equal(
      resolveVisibleBulkActionsForSelection(qcPermissions, [eligibleSelection]).some(
        (action) => action.key === "MARK_QC_PASSED",
      ),
      true,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(qcPermissions, alreadyPassed).length,
      0,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(qcPermissions, local).length,
      0,
    );
  });

  it("builds mark QC passed confirmation with skipped count", () => {
    assert.match(
      buildBulkMarkQcPassedConfirmationMessage(2, 1),
      /physical quality checks have been completed for 2 China orders/,
    );
    assert.match(
      buildBulkMarkQcPassedConfirmationMessage(2, 1),
      /1 selected order will be skipped/,
    );
    assert.equal(resolveBulkSuccessLabel("MARK_QC_PASSED"), "QC Passed");
  });
});

describe("China bulk MARK_CHINA_PACKING_COMPLETE", () => {
  const packingPermissions = ["warehouse.jobs.update", "warehouse.jobs.complete"];

  const eligibleSelection = {
    id: "ff-china-pack-1",
    strategy: "china",
    status: "processing",
    delivery_type: "company_shipping",
    warehouse_status: "pending",
    china: {
      stage: "qc_passed",
      qc_status: "passed",
    },
  };

  it("evaluates mark china packing complete eligibility helpers", () => {
    assert.equal(isEligibleForBulkMarkChinaPackingComplete(eligibleSelection), true);
    assert.equal(
      canBulkChinaAction("MARK_CHINA_PACKING_COMPLETE", eligibleSelection, packingPermissions),
      true,
    );
    assert.equal(
      isEligibleForBulkMarkChinaPackingComplete({
        ...eligibleSelection,
        strategy: "local",
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkMarkChinaPackingComplete({
        ...eligibleSelection,
        china: { ...eligibleSelection.china, qc_status: "pending" },
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkMarkChinaPackingComplete({
        ...eligibleSelection,
        warehouse_status: "packed",
      }),
      false,
    );
  });

  it("shows mark packing complete only for eligible China orders", () => {
    const qcPending = [
      {
        id: "ff-china-pack-2",
        strategy: "china",
        status: "processing",
        delivery_type: "company_shipping",
        warehouse_status: "pending",
        china: {
          stage: "qc_pending",
          qc_status: "pending",
        },
      },
    ];
    const local = [
      {
        id: "ff-local-1",
        strategy: "local",
        status: "processing",
        delivery_type: "self_pickup",
        warehouse_status: "pending",
      },
    ];

    assert.equal(countEligibleForBulkMarkChinaPackingComplete([eligibleSelection]), 1);
    assert.equal(
      resolveVisibleBulkActionsForSelection(packingPermissions, [eligibleSelection]).some(
        (action) => action.key === "MARK_CHINA_PACKING_COMPLETE",
      ),
      true,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(packingPermissions, qcPending).length,
      0,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(packingPermissions, local).some(
        (action) => action.key === "MARK_LOCAL_ORDER_READY",
      ),
      true,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(packingPermissions, local).some(
        (action) => action.key === "MARK_CHINA_PACKING_COMPLETE",
      ),
      false,
    );
  });

  it("builds mark china packing complete confirmation with skipped count", () => {
    assert.match(
      buildBulkMarkChinaPackingCompleteConfirmationMessage(2, 1),
      /complete warehouse packing for 2 China orders/,
    );
    assert.match(
      buildBulkMarkChinaPackingCompleteConfirmationMessage(2, 1),
      /1 selected order will be skipped/,
    );
    assert.equal(resolveBulkSuccessLabel("MARK_CHINA_PACKING_COMPLETE"), "Packed");
  });
});

describe("China bulk MARK_EXPORT_READY", () => {
  const exportPermissions = ["warehouse.jobs.complete"];

  const eligibleSelection = {
    id: "ff-china-export-1",
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

  it("evaluates mark export ready eligibility helpers", () => {
    assert.equal(isEligibleForBulkMarkExportReady(eligibleSelection), true);
    assert.equal(
      canBulkChinaAction("MARK_EXPORT_READY", eligibleSelection, exportPermissions),
      true,
    );
    assert.equal(
      isEligibleForBulkMarkExportReady({
        ...eligibleSelection,
        strategy: "local",
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkMarkExportReady({
        ...eligibleSelection,
        delivery_type: "customer_agent",
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkMarkExportReady({
        ...eligibleSelection,
        china: { ...eligibleSelection.china, export_ready: true },
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkMarkExportReady({
        ...eligibleSelection,
        warehouse_status: "pending",
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkMarkExportReady({
        ...eligibleSelection,
        warehouse_status: "packed",
      }),
      false,
    );
  });

  it("shows mark export ready only for eligible China company shipping orders", () => {
    const customerAgent = [
      {
        id: "ff-china-export-2",
        strategy: "china",
        status: "processing",
        delivery_type: "customer_agent",
        warehouse_status: "ready_to_ship",
        china: {
          stage: "qc_passed",
          qc_status: "passed",
          export_ready: false,
        },
      },
    ];
    const alreadyReady = [
      {
        id: "ff-china-export-3",
        strategy: "china",
        status: "processing",
        delivery_type: "company_shipping",
        warehouse_status: "ready_to_ship",
        china: {
          stage: "export_ready",
          qc_status: "passed",
          export_ready: true,
        },
      },
    ];
    const local = [
      {
        id: "ff-local-1",
        strategy: "local",
        status: "processing",
        delivery_type: "self_pickup",
        warehouse_status: "ready_to_ship",
      },
    ];

    assert.equal(countEligibleForBulkMarkExportReady([eligibleSelection]), 1);
    assert.equal(
      resolveVisibleBulkActionsForSelection(exportPermissions, [eligibleSelection]).some(
        (action) => action.key === "MARK_EXPORT_READY",
      ),
      true,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(exportPermissions, customerAgent).length,
      0,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(exportPermissions, alreadyReady).length,
      0,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(exportPermissions, local).length,
      0,
    );
  });

  it("builds mark export ready confirmation with skipped count", () => {
    assert.match(
      buildBulkMarkExportReadyConfirmationMessage(2, 1),
      /mark 2 China orders as export ready/,
    );
    assert.match(
      buildBulkMarkExportReadyConfirmationMessage(2, 1),
      /1 selected order will be skipped/,
    );
    assert.equal(resolveBulkSuccessLabel("MARK_EXPORT_READY"), "Export Ready");
  });
});

describe("China bulk MARK_AGENT_DELIVERED", () => {
  const shipPermissions = ["orders.ship"];

  const eligibleSelection = {
    id: "ff-china-agent-1",
    strategy: "china",
    status: "processing",
    delivery_type: "customer_agent",
    warehouse_status: "packed",
    china: {
      stage: "qc_passed",
      qc_status: "passed",
    },
  };

  it("evaluates mark agent delivered eligibility helpers", () => {
    assert.equal(isEligibleForBulkMarkAgentDelivered(eligibleSelection), true);
    assert.equal(
      canBulkChinaAction("MARK_AGENT_DELIVERED", eligibleSelection, shipPermissions),
      true,
    );
    assert.equal(
      isEligibleForBulkMarkAgentDelivered({
        ...eligibleSelection,
        strategy: "local",
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkMarkAgentDelivered({
        ...eligibleSelection,
        delivery_type: "company_shipping",
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkMarkAgentDelivered({
        ...eligibleSelection,
        status: "delivered",
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkMarkAgentDelivered({
        ...eligibleSelection,
        warehouse_status: "pending",
      }),
      false,
    );
  });

  it("shows deliver to customer agent only for eligible China customer agent orders", () => {
    const companyShipping = [
      {
        id: "ff-china-agent-2",
        strategy: "china",
        status: "processing",
        delivery_type: "company_shipping",
        warehouse_status: "packed",
        china: {
          stage: "qc_passed",
          qc_status: "passed",
        },
      },
    ];
    const local = [
      {
        id: "ff-local-1",
        strategy: "local",
        status: "processing",
        delivery_type: "self_pickup",
        warehouse_status: "packed",
      },
    ];
    const alreadyDelivered = [
      {
        id: "ff-china-agent-3",
        strategy: "china",
        status: "delivered",
        delivery_type: "customer_agent",
        warehouse_status: "packed",
        china: {
          stage: "qc_passed",
          qc_status: "passed",
        },
      },
    ];

    assert.equal(countEligibleForBulkMarkAgentDelivered([eligibleSelection]), 1);
    assert.equal(
      resolveVisibleBulkActionsForSelection(shipPermissions, [eligibleSelection]).some(
        (action) => action.key === "MARK_AGENT_DELIVERED",
      ),
      true,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(shipPermissions, companyShipping).length,
      0,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(shipPermissions, local).length,
      0,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(shipPermissions, alreadyDelivered).length,
      0,
    );
  });

  it("builds mark agent delivered confirmation with skipped count", () => {
    assert.match(
      buildBulkMarkAgentDeliveredConfirmationMessage(2, 1),
      /deliver 2 China orders to customer agents/,
    );
    assert.match(
      buildBulkMarkAgentDeliveredConfirmationMessage(2, 1),
      /1 selected order will be skipped/,
    );
    assert.equal(resolveBulkSuccessLabel("MARK_AGENT_DELIVERED"), "Delivered");
  });
});

describe("China bulk CREATE_SHIPMENT", () => {
  const shipPermissions = ["orders.ship"];

  const eligibleSelection = {
    id: "ff-china-ship-1",
    strategy: "china",
    status: "ready_for_shipping",
    delivery_type: "company_shipping",
    warehouse_status: "ready_to_ship",
    shipment_status: "",
    china: {
      stage: "company_shipping_ready",
      qc_status: "passed",
      export_ready: true,
    },
  };

  it("evaluates create shipment eligibility helpers", () => {
    assert.equal(isEligibleForBulkCreateShipment(eligibleSelection), true);
    assert.equal(
      canBulkChinaAction("CREATE_SHIPMENT", eligibleSelection, shipPermissions),
      true,
    );
    assert.equal(
      isEligibleForBulkCreateShipment({
        ...eligibleSelection,
        delivery_type: "customer_agent",
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkCreateShipment({
        ...eligibleSelection,
        strategy: "local",
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkCreateShipment({
        ...eligibleSelection,
        china: { ...eligibleSelection.china, export_ready: false },
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkCreateShipment({
        ...eligibleSelection,
        shipment_status: "pending",
      }),
      false,
    );
    assert.equal(hasExistingShipment({ ...eligibleSelection, shipment_status: "pending" }), true);
    assert.equal(
      isEligibleForBulkCreateShipment({
        ...eligibleSelection,
        status: "shipped",
      }),
      false,
    );
  });

  it("shows create shipments only for eligible China company shipping orders", () => {
    const customerAgent = [
      {
        id: "ff-china-ship-2",
        strategy: "china",
        status: "ready_for_shipping",
        delivery_type: "customer_agent",
        warehouse_status: "ready_to_ship",
        shipment_status: "",
        china: {
          stage: "company_shipping_ready",
          qc_status: "passed",
          export_ready: true,
        },
      },
    ];
    const local = [
      {
        id: "ff-local-1",
        strategy: "local",
        status: "ready_for_shipping",
        delivery_type: "company_shipping",
        warehouse_status: "ready_to_ship",
        shipment_status: "",
      },
    ];
    const exportPending = [
      {
        id: "ff-china-ship-3",
        strategy: "china",
        status: "ready_for_shipping",
        delivery_type: "company_shipping",
        warehouse_status: "ready_to_ship",
        shipment_status: "",
        china: {
          stage: "qc_passed",
          qc_status: "passed",
          export_ready: false,
        },
      },
    ];
    const alreadyShipped = [
      {
        id: "ff-china-ship-4",
        strategy: "china",
        status: "ready_for_shipping",
        delivery_type: "company_shipping",
        warehouse_status: "ready_to_ship",
        shipment_status: "pending",
        china: {
          stage: "company_shipping_ready",
          qc_status: "passed",
          export_ready: true,
        },
      },
    ];

    assert.equal(countEligibleForBulkCreateShipment([eligibleSelection]), 1);
    assert.equal(
      resolveVisibleBulkActionsForSelection(shipPermissions, [eligibleSelection]).some(
        (action) => action.key === "CREATE_SHIPMENT",
      ),
      true,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(shipPermissions, customerAgent).some(
        (action) => action.key === "CREATE_SHIPMENT",
      ),
      false,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(shipPermissions, local).some(
        (action) => action.key === "CREATE_SHIPMENT",
      ),
      false,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(shipPermissions, exportPending).some(
        (action) => action.key === "CREATE_SHIPMENT",
      ),
      false,
    );
    assert.equal(
      resolveVisibleBulkActionsForSelection(shipPermissions, alreadyShipped).some(
        (action) => action.key === "CREATE_SHIPMENT",
      ),
      false,
    );
  });

  it("builds create shipment confirmation with skipped count", () => {
    assert.match(
      buildBulkCreateShipmentConfirmationMessage(2, 1),
      /create shipments for 2 China orders/,
    );
    assert.match(
      buildBulkCreateShipmentConfirmationMessage(2, 1),
      /1 selected order will be skipped/,
    );
    assert.equal(resolveBulkSuccessLabel("CREATE_SHIPMENT"), "Created");
  });
});

describe("China bulk MARK_CUSTOMER_COLLECTED / MARK_CUSTOMER_DELIVERED", () => {
  const fulfillPermissions = ["orders.fulfill"];

  const pickupReady = {
    id: "ff-handover-pickup",
    strategy: "china",
    status: "shipped",
    delivery_type: "company_shipping",
    shipment_arrived_at: "2026-07-28T08:00:00.000Z",
    last_mile_receiving_method: "self_pickup",
  };

  const deliveryReady = {
    ...pickupReady,
    id: "ff-handover-delivery",
    last_mile_receiving_method: "negotiated_delivery",
  };

  it("evaluates handover bulk eligibility helpers", () => {
    assert.equal(isEligibleForBulkMarkCustomerCollected(pickupReady), true);
    assert.equal(isEligibleForBulkMarkCustomerDelivered(deliveryReady), true);
    assert.equal(
      canBulkChinaAction("MARK_CUSTOMER_COLLECTED", pickupReady, fulfillPermissions),
      true,
    );
    assert.equal(isEligibleForBulkMarkCustomerCollected(deliveryReady), false);
    assert.equal(isEligibleForBulkMarkCustomerDelivered(pickupReady), false);
    assert.equal(
      isEligibleForBulkMarkCustomerCollected({
        ...pickupReady,
        strategy: "local",
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkMarkCustomerCollected({
        ...pickupReady,
        delivery_type: "customer_agent",
      }),
      false,
    );
    assert.equal(
      isEligibleForBulkMarkCustomerCollected({
        ...pickupReady,
        status: "delivered",
      }),
      false,
    );
  });

  it("maps admin fulfilment rows into bulk selection context", () => {
    const context = mapAdminFulfillmentToBulkSelectionContext({
      id: "ff-1",
      strategy: "china",
      status: "shipped",
      shipment_arrived_at: "2026-07-28T08:00:00.000Z",
      order: {
        id: "ord-1",
        order_number: "COTZ-001",
        delivery_type: "company_shipping",
        last_mile_receiving_method: "self_pickup",
      },
    });

    assert.equal(context.shipment_arrived_at, "2026-07-28T08:00:00.000Z");
    assert.equal(context.last_mile_receiving_method, "self_pickup");
  });

  it("shows handover bulk actions only for eligible selections", () => {
    const visible = resolveVisibleBulkActionsForSelection(fulfillPermissions, [
      pickupReady,
      deliveryReady,
      {
        id: "ff-local",
        strategy: "local",
        status: "ready_for_shipping",
        delivery_type: "self_pickup",
      },
    ]);

    assert.ok(visible.some((action) => action.key === "MARK_CUSTOMER_COLLECTED"));
    assert.ok(visible.some((action) => action.key === "MARK_CUSTOMER_DELIVERED"));
    assert.equal(
      buildBulkMarkCustomerCollectedConfirmationMessage(1, 2).includes("will be skipped"),
      true,
    );
    assert.equal(resolveBulkSuccessLabel("MARK_CUSTOMER_COLLECTED"), "Completed");
    assert.equal(resolveBulkResultReasonLabel("INVALID_METHOD"), "Wrong receiving method");
  });
});

describe("bulk result reporting hardening", () => {
  it("groups skipped and failed reasons for summary display", () => {
    const groupedSkipped = groupBulkResultReasons(
      [
        {
          fulfillment_id: "ff-1",
          status: "skipped",
          success: false,
          reason_code: "ALREADY_COMPLETED",
          reason: "Fulfilment is already completed or cancelled.",
        },
        {
          fulfillment_id: "ff-2",
          status: "skipped",
          success: false,
          reason_code: "ALREADY_COMPLETED",
          reason: "Fulfilment is already completed or cancelled.",
        },
        {
          fulfillment_id: "ff-3",
          status: "failed",
          success: false,
          reason_code: "MISSING_SUPPLIER",
          reason: "Missing supplier mapping.",
        },
      ],
      "skipped",
    );

    assert.deepEqual(groupedSkipped, [
      {
        reason_code: "ALREADY_COMPLETED",
        label: "Already completed",
        count: 2,
      },
    ]);

    const groupedFailed = groupBulkResultReasons(
      [
        {
          fulfillment_id: "ff-3",
          status: "failed",
          success: false,
          reason_code: "MISSING_SUPPLIER",
          reason: "Missing supplier mapping.",
        },
      ],
      "failed",
    );

    assert.deepEqual(groupedFailed, [
      {
        reason_code: "MISSING_SUPPLIER",
        label: "Missing supplier mapping",
        count: 1,
      },
    ]);
  });

  it("warns for large bulk selections without blocking execution", () => {
    assert.equal(shouldWarnLargeBulkSelection(50), false);
    assert.equal(shouldWarnLargeBulkSelection(51), true);
    assert.match(buildLargeBulkSelectionWarning(75), /more than 50 orders/);
  });
});
