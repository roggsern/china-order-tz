"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  AdminProcurementApiError,
  createAdminPurchaseOrder,
  fetchAdminPurchaseOrder,
  fetchAdminPurchaseOrders,
  fetchAdminSuppliers,
  receiveAdminPurchaseOrder,
  updateAdminPurchaseOrderStatus,
  type AdminPurchaseOrder,
  type AdminSupplier,
} from "@/lib/api/admin-procurement";

const STATUS_STYLES: Record<string, string> = {
  draft: "bg-zinc-100 text-zinc-700",
  sent: "bg-sky-50 text-sky-800",
  confirmed: "bg-blue-50 text-blue-800",
  partially_received: "bg-amber-50 text-amber-800",
  completed: "bg-green-50 text-green-800",
  cancelled: "bg-red-50 text-red-800",
};

const STATUS_ACTIONS: Record<string, Array<{ status: string; label: string }>> = {
  draft: [{ status: "sent", label: "Mark sent" }],
  sent: [{ status: "confirmed", label: "Confirm" }],
  confirmed: [{ status: "cancelled", label: "Cancel" }],
  partially_received: [{ status: "cancelled", label: "Cancel" }],
};

export function AdminPurchaseOrdersPanel() {
  const [rows, setRows] = useState<AdminPurchaseOrder[]>([]);
  const [suppliers, setSuppliers] = useState<AdminSupplier[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [statusFilter, setStatusFilter] = useState("all");
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [detail, setDetail] = useState<AdminPurchaseOrder | null>(null);
  const [receiveQty, setReceiveQty] = useState<Record<string, string>>({});
  const [createForm, setCreateForm] = useState({
    supplier_id: "",
    product_variant_id: "",
    quantity_ordered: "10",
    unit_cost: "10000",
    notes: "",
  });

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [orders, supplierRows] = await Promise.all([
        fetchAdminPurchaseOrders({
          status: statusFilter === "all" ? undefined : statusFilter,
        }),
        fetchAdminSuppliers({ isActive: true }),
      ]);
      setRows(orders);
      setSuppliers(supplierRows);
    } catch (err) {
      setRows([]);
      setError(
        err instanceof AdminProcurementApiError ? err.message : "Unable to load purchase orders.",
      );
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const loadDetail = useCallback(async (id: string) => {
    setSelectedId(id);
    setError(null);
    try {
      const order = await fetchAdminPurchaseOrder(id);
      setDetail(order);
      const qty: Record<string, string> = {};
      for (const item of order.items ?? []) {
        qty[item.id] = String(item.quantity_outstanding || "");
      }
      setReceiveQty(qty);
    } catch (err) {
      setDetail(null);
      setError(
        err instanceof AdminProcurementApiError ? err.message : "Unable to load purchase order.",
      );
    }
  }, []);

  const onCreate = async (event: React.FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const order = await createAdminPurchaseOrder({
        supplier_id: createForm.supplier_id,
        notes: createForm.notes.trim() || null,
        currency: "TZS",
        items: [
          {
            product_variant_id: createForm.product_variant_id.trim(),
            quantity_ordered: Number(createForm.quantity_ordered),
            unit_cost: Number(createForm.unit_cost),
          },
        ],
      });
      setCreateForm((f) => ({
        ...f,
        product_variant_id: "",
        notes: "",
      }));
      await reload();
      await loadDetail(order.id);
    } catch (err) {
      setError(
        err instanceof AdminProcurementApiError ? err.message : "Unable to create purchase order.",
      );
    } finally {
      setBusy(false);
    }
  };

  const onStatus = async (status: string) => {
    if (!detail) return;
    setBusy(true);
    setError(null);
    try {
      const updated = await updateAdminPurchaseOrderStatus(detail.id, status);
      setDetail(updated);
      await reload();
    } catch (err) {
      setError(
        err instanceof AdminProcurementApiError ? err.message : "Unable to update status.",
      );
    } finally {
      setBusy(false);
    }
  };

  const onReceive = async () => {
    if (!detail) return;
    const items = (detail.items ?? [])
      .map((item) => ({
        purchase_order_item_id: item.id,
        quantity: Number(receiveQty[item.id] || 0),
      }))
      .filter((line) => line.quantity > 0);

    if (items.length === 0) {
      setError("Enter at least one quantity to receive.");
      return;
    }

    setBusy(true);
    setError(null);
    try {
      const result = await receiveAdminPurchaseOrder(detail.id, { items });
      if (result.purchase_order) {
        setDetail(result.purchase_order);
      } else {
        await loadDetail(detail.id);
      }
      await reload();
    } catch (err) {
      setError(err instanceof AdminProcurementApiError ? err.message : "Unable to receive goods.");
    } finally {
      setBusy(false);
    }
  };

  const actions = useMemo(
    () => (detail ? STATUS_ACTIONS[detail.status] ?? [] : []),
    [detail],
  );

  const canReceive =
    detail?.status === "confirmed" || detail?.status === "partially_received";

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-xl font-semibold text-zinc-900">Purchase Orders</h1>
        <p className="mt-1 text-sm text-zinc-500">
          Create POs, confirm with suppliers, and receive goods into VariantInventory.
        </p>
      </div>

      {error ? (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      ) : null}

      <section className="admin-card p-5">
        <h2 className="text-sm font-semibold text-zinc-900">Create purchase order</h2>
        <form onSubmit={(e) => void onCreate(e)} className="mt-4 grid gap-3 sm:grid-cols-2">
          <select
            className="admin-input"
            value={createForm.supplier_id}
            onChange={(e) => setCreateForm((f) => ({ ...f, supplier_id: e.target.value }))}
            required
          >
            <option value="">Supplier *</option>
            {suppliers.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name} ({s.code})
              </option>
            ))}
          </select>
          <input
            className="admin-input"
            placeholder="Product variant UUID *"
            value={createForm.product_variant_id}
            onChange={(e) => setCreateForm((f) => ({ ...f, product_variant_id: e.target.value }))}
            required
          />
          <input
            className="admin-input"
            type="number"
            min={1}
            placeholder="Qty ordered *"
            value={createForm.quantity_ordered}
            onChange={(e) => setCreateForm((f) => ({ ...f, quantity_ordered: e.target.value }))}
            required
          />
          <input
            className="admin-input"
            type="number"
            min={0}
            step="0.01"
            placeholder="Unit cost *"
            value={createForm.unit_cost}
            onChange={(e) => setCreateForm((f) => ({ ...f, unit_cost: e.target.value }))}
            required
          />
          <input
            className="admin-input sm:col-span-2"
            placeholder="Notes"
            value={createForm.notes}
            onChange={(e) => setCreateForm((f) => ({ ...f, notes: e.target.value }))}
          />
          <button type="submit" disabled={busy} className="admin-btn-primary sm:col-span-2">
            Create draft PO
          </button>
        </form>
      </section>

      <div className="grid gap-6 lg:grid-cols-[1fr_380px]">
        <section className="admin-card overflow-hidden">
          <div className="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-3">
            <h2 className="text-sm font-semibold text-zinc-900">Orders</h2>
            <select
              className="admin-input w-auto py-1.5 text-xs"
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
            >
              <option value="all">All statuses</option>
              <option value="draft">Draft</option>
              <option value="sent">Sent</option>
              <option value="confirmed">Confirmed</option>
              <option value="partially_received">Partially received</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          {loading ? (
            <p className="p-5 text-sm text-zinc-500">Loading…</p>
          ) : rows.length === 0 ? (
            <p className="p-5 text-sm text-zinc-500">No purchase orders.</p>
          ) : (
            <ul className="divide-y divide-zinc-100">
              {rows.map((order) => (
                <li key={order.id}>
                  <button
                    type="button"
                    onClick={() => void loadDetail(order.id)}
                    className={`flex w-full items-center justify-between gap-3 px-5 py-3 text-left hover:bg-zinc-50 ${
                      selectedId === order.id ? "bg-amber-50/50" : ""
                    }`}
                  >
                    <div>
                      <p className="font-medium text-zinc-900">{order.purchase_number}</p>
                      <p className="text-xs text-zinc-500">
                        {order.supplier?.name ?? "Supplier"} · {order.items?.length ?? 0} line(s)
                      </p>
                    </div>
                    <span
                      className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${
                        STATUS_STYLES[order.status] ?? "bg-zinc-100 text-zinc-600"
                      }`}
                    >
                      {order.status.replaceAll("_", " ")}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </section>

        <section className="admin-card p-5">
          <h2 className="text-sm font-semibold text-zinc-900">Receiving panel</h2>
          {!detail ? (
            <p className="mt-3 text-sm text-zinc-500">Select a purchase order to manage receiving.</p>
          ) : (
            <div className="mt-4 space-y-4">
              <div>
                <p className="font-semibold text-zinc-900">{detail.purchase_number}</p>
                <p className="text-xs text-zinc-500">
                  {detail.supplier?.name} · {detail.status.replaceAll("_", " ")}
                </p>
              </div>

              {actions.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                  {actions.map((action) => (
                    <button
                      key={action.status}
                      type="button"
                      disabled={busy}
                      onClick={() => void onStatus(action.status)}
                      className="rounded-md bg-zinc-900 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                    >
                      {action.label}
                    </button>
                  ))}
                </div>
              ) : null}

              <div className="space-y-3">
                {(detail.items ?? []).map((item) => (
                  <div key={item.id} className="rounded-lg border border-zinc-200 p-3">
                    <p className="text-sm font-medium text-zinc-900">
                      {item.variant?.product?.name || item.variant?.name || "Variant"}
                    </p>
                    <p className="text-xs text-zinc-500">
                      Ordered {item.quantity_ordered} · Received {item.quantity_received} ·
                      Outstanding {item.quantity_outstanding}
                    </p>
                    {canReceive ? (
                      <input
                        className="admin-input mt-2"
                        type="number"
                        min={0}
                        max={item.quantity_outstanding}
                        placeholder="Receive qty"
                        value={receiveQty[item.id] ?? ""}
                        onChange={(e) =>
                          setReceiveQty((q) => ({ ...q, [item.id]: e.target.value }))
                        }
                      />
                    ) : null}
                  </div>
                ))}
              </div>

              {canReceive ? (
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => void onReceive()}
                  className="admin-btn-primary w-full"
                >
                  Complete receiving
                </button>
              ) : (
                <p className="text-xs text-zinc-500">
                  Confirm the purchase order before receiving goods. Inventory updates only after
                  receiving completes.
                </p>
              )}
            </div>
          )}
        </section>
      </div>
    </div>
  );
}
