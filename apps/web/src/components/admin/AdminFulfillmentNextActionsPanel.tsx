"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  resolveActionConfirmationCopy,
  resolveActionImpact,
  resolveFulfillmentAvailableActions,
  selectPrimaryFulfillmentAction,
  shouldShowNextActionsPanel,
  type CustomerAgentOperationalState,
  type FulfillmentActionPurchaseOrder,
  type FulfillmentAvailableAction,
} from "@/lib/admin/fulfillment-available-actions";
import type { FulfillmentOperationalModel } from "@/lib/admin/fulfillment-operational";
import {
  AdminFulfillmentActionError,
  executeFulfillmentAction,
  fetchAdminChinaWorkflowPurchaseOrders,
  fetchAdminCustomerAgentPickup,
} from "@/lib/api/admin-fulfillment-actions";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";

interface AdminFulfillmentNextActionsPanelProps {
  model: FulfillmentOperationalModel;
  onRefresh: () => Promise<void>;
}

function ActionCard({
  action,
  busy,
  disabled,
  onClick,
}: {
  action: FulfillmentAvailableAction;
  busy: boolean;
  disabled: boolean;
  onClick: () => void;
}) {
  const impact = resolveActionImpact(action);

  return (
    <article className="rounded-xl border border-zinc-100 bg-zinc-50/50 p-4">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-sm font-bold text-zinc-900">{action.label}</h3>
            {action.requires_confirmation ? (
              <span className="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-900 ring-1 ring-amber-200">
                Confirmation required
              </span>
            ) : null}
          </div>
          <p className="mt-1 text-sm text-zinc-600">{action.description}</p>
          {!action.available && action.unavailable_reason ? (
            <p className="mt-2 text-sm text-amber-800">{action.unavailable_reason}</p>
          ) : null}
          <p className="mt-2 text-xs font-medium text-zinc-500">
            Impact: <span className="font-normal text-zinc-700">{impact}</span>
          </p>
          {!action.available && action.unavailable_reason ? (
            <p className="mt-2 text-xs text-amber-800">{action.unavailable_reason}</p>
          ) : null}
        </div>
        <button
          type="button"
          disabled={disabled || !action.available}
          onClick={onClick}
          className="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-zinc-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60 lg:min-w-[160px]"
        >
          {busy ? `${action.label}…` : action.label}
        </button>
      </div>
    </article>
  );
}

export function AdminFulfillmentNextActionsPanel({
  model,
  onRefresh,
}: AdminFulfillmentNextActionsPanelProps) {
  const [loadingContext, setLoadingContext] = useState(false);
  const [busyActionKey, setBusyActionKey] = useState<string | null>(null);
  const [feedback, setFeedback] = useState<{ tone: "success" | "error"; message: string } | null>(
    null,
  );
  const [pendingAction, setPendingAction] = useState<FulfillmentAvailableAction | null>(null);
  const [customerAgent, setCustomerAgent] = useState<CustomerAgentOperationalState>(null);
  const [purchaseOrders, setPurchaseOrders] = useState<FulfillmentActionPurchaseOrder[]>([]);
  const { permissions } = useAdminPermissions();

  const orderId = model.order?.id;
  const deliveryType = model.order?.delivery_type ?? null;
  const needsCustomerAgent = deliveryType === "customer_agent";
  const isChinaFulfilment =
    model.fulfillment.strategy === "china" &&
    !["delivered", "cancelled"].includes(model.fulfillment.status);
  const needsPurchaseOrders =
    isChinaFulfilment ||
    (model.china?.procurement?.some((row) => (row.supplier_response ?? "pending") === "pending") ??
      false);

  useEffect(() => {
    let cancelled = false;

    async function loadContext() {
      if (!orderId || (!needsCustomerAgent && !needsPurchaseOrders)) {
        setCustomerAgent(null);
        setPurchaseOrders([]);
        return;
      }

      setLoadingContext(true);
      try {
        const [agentState, pos] = await Promise.all([
          needsCustomerAgent ? fetchAdminCustomerAgentPickup(orderId) : Promise.resolve(null),
          needsPurchaseOrders
            ? fetchAdminChinaWorkflowPurchaseOrders(orderId)
            : Promise.resolve([]),
        ]);
        if (!cancelled) {
          setCustomerAgent(agentState);
          setPurchaseOrders(pos);
        }
      } catch {
        if (!cancelled) {
          setCustomerAgent(null);
          setPurchaseOrders([]);
        }
      } finally {
        if (!cancelled) {
          setLoadingContext(false);
        }
      }
    }

    void loadContext();
    return () => {
      cancelled = true;
    };
  }, [orderId, needsCustomerAgent, needsPurchaseOrders, model.fulfillment.updated_at]);

  const actions = useMemo(
    () =>
      resolveFulfillmentAvailableActions({
        model,
        permissions,
        customerAgent,
        purchaseOrders,
      }),
    [model, permissions, customerAgent, purchaseOrders],
  );

  const visibleActions = useMemo(() => {
    const primary = selectPrimaryFulfillmentAction(actions);
    return primary ? [primary] : [];
  }, [actions]);

  const runAction = useCallback(
    async (action: FulfillmentAvailableAction) => {
      if (busyActionKey) {
        return;
      }

      setFeedback(null);
      setBusyActionKey(action.key);
      try {
        await executeFulfillmentAction(model, action);
        setFeedback({
          tone: "success",
          message: `${action.label} completed successfully.`,
        });
        await onRefresh();
      } catch (error) {
        setFeedback({
          tone: "error",
          message:
            error instanceof AdminFulfillmentActionError
              ? error.message
              : "Unable to complete action. Please try again.",
        });
      } finally {
        setBusyActionKey(null);
        setPendingAction(null);
      }
    },
    [busyActionKey, model, onRefresh],
  );

  const handleActionClick = (action: FulfillmentAvailableAction) => {
    if (busyActionKey || !action.available) {
      return;
    }

    if (action.requires_confirmation) {
      setPendingAction(action);
      return;
    }

    void runAction(action);
  };

  if (!shouldShowNextActionsPanel(actions) && !loadingContext) {
    return null;
  }

  const confirmation = pendingAction ? resolveActionConfirmationCopy(pendingAction) : null;

  return (
    <section className="admin-card p-4 sm:p-5" aria-labelledby="fulfillment-next-actions-title">
      <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 id="fulfillment-next-actions-title" className="text-sm font-bold text-zinc-900">
            Next actions
          </h2>
          <p className="mt-1 text-xs text-zinc-500">
            The next recommended operational step for this fulfilment.
          </p>
        </div>
        {loadingContext ? (
          <p className="text-xs font-medium text-zinc-500">Loading action context…</p>
        ) : null}
      </div>

      {feedback ? (
        <p
          role={feedback.tone === "error" ? "alert" : "status"}
          className={`mt-4 rounded-lg px-3 py-2 text-sm ${
            feedback.tone === "success"
              ? "bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100"
              : "bg-red-50 text-red-700 ring-1 ring-red-100"
          }`}
        >
          {feedback.message}
        </p>
      ) : null}

      <div className="mt-4 space-y-3">
        {visibleActions.map((action) => (
          <ActionCard
            key={action.key}
            action={action}
            busy={busyActionKey === action.key}
            disabled={Boolean(busyActionKey) || loadingContext}
            onClick={() => handleActionClick(action)}
          />
        ))}
      </div>

      {confirmation && pendingAction ? (
        <div
          className="fixed inset-0 z-50 flex items-end justify-center bg-zinc-900/40 p-4 sm:items-center"
          role="dialog"
          aria-modal="true"
          aria-labelledby="fulfillment-action-confirm-title"
        >
          <div className="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl ring-1 ring-zinc-200">
            <h3 id="fulfillment-action-confirm-title" className="text-base font-bold text-zinc-900">
              {confirmation.title}
            </h3>
            <p className="mt-2 text-sm text-zinc-600">{confirmation.message}</p>
            <p className="mt-3 text-xs text-zinc-500">
              Impact: {resolveActionImpact(pendingAction)}
            </p>
            <div className="mt-5 grid gap-2">
              <button
                type="button"
                disabled={Boolean(busyActionKey)}
                onClick={() => void runAction(pendingAction)}
                className="flex min-h-11 w-full items-center justify-center rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-zinc-800 disabled:opacity-60"
              >
                {busyActionKey === pendingAction.key ? "Processing…" : "Confirm"}
              </button>
              <button
                type="button"
                disabled={Boolean(busyActionKey)}
                onClick={() => setPendingAction(null)}
                className="flex min-h-11 w-full items-center justify-center rounded-lg border border-zinc-200 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-800 hover:bg-zinc-50 disabled:opacity-60"
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </section>
  );
}
