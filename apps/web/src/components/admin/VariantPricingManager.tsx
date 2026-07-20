"use client";

import { useCallback, useEffect, useState } from "react";
import {
  AdminCatalogApiError,
  createAdminVariantPrice,
  deleteAdminVariantPrice,
  fetchAdminVariantPrices,
  updateAdminVariantPrice,
  type AdminVariantPrice,
  type VariantPriceType,
} from "@/lib/api/admin-catalog";

type VariantPricingManagerProps = {
  variantId: string;
  variantLabel: string;
  onClose?: () => void;
};

type PriceForm = {
  priceType: VariantPriceType;
  currency: string;
  amount: string;
  compareAtPrice: string;
  costPrice: string;
  minimumQuantity: string;
  isActive: boolean;
  startsAt: string;
  endsAt: string;
};

const emptyForm = (): PriceForm => ({
  priceType: "retail",
  currency: "TZS",
  amount: "",
  compareAtPrice: "",
  costPrice: "",
  minimumQuantity: "1",
  isActive: true,
  startsAt: "",
  endsAt: "",
});

function toDatetimeLocal(value: string | null): string {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function fromDatetimeLocal(value: string): string | null {
  if (!value.trim()) return null;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return null;
  return date.toISOString();
}

export function VariantPricingManager({
  variantId,
  variantLabel,
  onClose,
}: VariantPricingManagerProps) {
  const [prices, setPrices] = useState<AdminVariantPrice[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState<PriceForm>(emptyForm());

  const reload = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      setPrices(await fetchAdminVariantPrices(variantId));
    } catch (err) {
      setPrices([]);
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to load prices.",
      );
    } finally {
      setIsLoading(false);
    }
  }, [variantId]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const startCreate = () => {
    setEditingId(null);
    setForm(emptyForm());
    setSuccess(null);
    setError(null);
  };

  const startEdit = (price: AdminVariantPrice) => {
    setEditingId(price.id);
    setForm({
      priceType: price.priceType,
      currency: price.currency,
      amount: String(price.amount),
      compareAtPrice:
        price.compareAtPrice === null ? "" : String(price.compareAtPrice),
      costPrice: price.costPrice === null ? "" : String(price.costPrice),
      minimumQuantity: String(price.minimumQuantity),
      isActive: price.isActive,
      startsAt: toDatetimeLocal(price.startsAt),
      endsAt: toDatetimeLocal(price.endsAt),
    });
    setSuccess(null);
    setError(null);
  };

  const handleSave = async () => {
    const amount = Number(form.amount);
    if (!Number.isFinite(amount) || amount < 0) {
      setError("Amount must be a valid non-negative number.");
      return;
    }

    setBusy(true);
    setError(null);
    setSuccess(null);

    const body = {
      price_type: form.priceType,
      currency: form.currency.trim().toUpperCase(),
      amount,
      compare_at_price:
        form.compareAtPrice.trim() === "" ? null : Number(form.compareAtPrice),
      cost_price: form.costPrice.trim() === "" ? null : Number(form.costPrice),
      minimum_quantity: Math.max(1, Number(form.minimumQuantity) || 1),
      is_active: form.isActive,
      starts_at: fromDatetimeLocal(form.startsAt),
      ends_at: fromDatetimeLocal(form.endsAt),
    };

    try {
      if (editingId) {
        await updateAdminVariantPrice(editingId, body);
        setSuccess("Price updated.");
      } else {
        await createAdminVariantPrice(variantId, body);
        setSuccess("Price created.");
      }
      setEditingId(null);
      setForm(emptyForm());
      await reload();
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to save price.",
      );
    } finally {
      setBusy(false);
    }
  };

  const handleToggleActive = async (price: AdminVariantPrice) => {
    setBusy(true);
    setError(null);
    try {
      await updateAdminVariantPrice(price.id, { is_active: !price.isActive });
      setSuccess(price.isActive ? "Price deactivated." : "Price activated.");
      await reload();
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to update price status.",
      );
    } finally {
      setBusy(false);
    }
  };

  const handleDelete = async (price: AdminVariantPrice) => {
    if (
      !window.confirm(
        `Delete ${price.priceType.toUpperCase()} ${price.currency} ${price.amount}?`,
      )
    ) {
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await deleteAdminVariantPrice(price.id);
      setSuccess("Price deleted.");
      if (editingId === price.id) {
        startCreate();
      }
      await reload();
    } catch (err) {
      setError(
        err instanceof AdminCatalogApiError
          ? err.message
          : "Unable to delete price.",
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-4 rounded-lg border border-zinc-200 bg-zinc-50/50 p-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h3 className="text-sm font-semibold text-zinc-900">Pricing</h3>
          <p className="text-xs text-zinc-500">{variantLabel}</p>
        </div>
        {onClose ? (
          <button
            type="button"
            className="text-xs text-zinc-500 hover:text-zinc-800"
            onClick={onClose}
          >
            Close pricing
          </button>
        ) : null}
      </div>

      <p className="text-xs text-zinc-500">
        Standalone Pricing Engine — retail / wholesale / dealer / vip across
        currencies. Discounts and tax are separate modules.
      </p>

      {error ? (
        <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
          {error}
        </div>
      ) : null}
      {success ? (
        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
          {success}
        </div>
      ) : null}

      {isLoading ? (
        <p className="text-sm text-zinc-500">Loading prices…</p>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
          <table className="min-w-full text-left text-sm">
            <thead className="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
              <tr>
                <th className="px-3 py-2 font-medium">Type</th>
                <th className="px-3 py-2 font-medium">Currency</th>
                <th className="px-3 py-2 font-medium">Amount</th>
                <th className="px-3 py-2 font-medium">Min qty</th>
                <th className="px-3 py-2 font-medium">Schedule</th>
                <th className="px-3 py-2 font-medium">Status</th>
                <th className="px-3 py-2 font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {prices.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-3 py-5 text-center text-zinc-500">
                    No prices yet for this variant.
                  </td>
                </tr>
              ) : (
                prices.map((price) => (
                  <tr key={price.id} className="border-t border-zinc-100">
                    <td className="px-3 py-2 font-medium capitalize text-zinc-900">
                      {price.priceType}
                    </td>
                    <td className="px-3 py-2 font-mono text-xs">{price.currency}</td>
                    <td className="px-3 py-2">{price.amount.toLocaleString()}</td>
                    <td className="px-3 py-2">{price.minimumQuantity}</td>
                    <td className="px-3 py-2 text-xs text-zinc-500">
                      {price.startsAt || price.endsAt
                        ? `${price.startsAt ? new Date(price.startsAt).toLocaleDateString() : "—"} → ${
                            price.endsAt
                              ? new Date(price.endsAt).toLocaleDateString()
                              : "—"
                          }`
                        : "Always"}
                    </td>
                    <td className="px-3 py-2">
                      <span
                        className={`rounded px-1.5 py-0.5 text-[11px] font-medium ${
                          price.isCurrentlyActive
                            ? "bg-emerald-50 text-emerald-700"
                            : "bg-zinc-100 text-zinc-600"
                        }`}
                      >
                        {price.isCurrentlyActive
                          ? "Live"
                          : price.isActive
                            ? "Scheduled"
                            : "Inactive"}
                      </span>
                    </td>
                    <td className="px-3 py-2">
                      <div className="flex flex-wrap gap-2">
                        <button
                          type="button"
                          className="text-xs font-medium text-zinc-700 hover:underline"
                          disabled={busy}
                          onClick={() => startEdit(price)}
                        >
                          Edit
                        </button>
                        <button
                          type="button"
                          className="text-xs font-medium text-zinc-700 hover:underline"
                          disabled={busy}
                          onClick={() => void handleToggleActive(price)}
                        >
                          {price.isActive ? "Deactivate" : "Activate"}
                        </button>
                        <button
                          type="button"
                          className="text-xs font-medium text-red-600 hover:underline"
                          disabled={busy}
                          onClick={() => void handleDelete(price)}
                        >
                          Delete
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}

      <div className="rounded-lg border border-zinc-200 bg-white p-3">
        <div className="flex items-center justify-between gap-2">
          <h4 className="text-sm font-semibold text-zinc-900">
            {editingId ? "Edit price" : "Create price"}
          </h4>
          {editingId ? (
            <button
              type="button"
              className="text-xs text-zinc-500 hover:text-zinc-800"
              onClick={startCreate}
            >
              Cancel edit
            </button>
          ) : null}
        </div>

        <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div>
            <label className="admin-label" htmlFor="price-type">
              Price type
            </label>
            <select
              id="price-type"
              className="admin-input mt-1"
              value={form.priceType}
              onChange={(event) =>
                setForm({
                  ...form,
                  priceType: event.target.value as VariantPriceType,
                })
              }
            >
              <option value="retail">Retail</option>
              <option value="wholesale">Wholesale</option>
              <option value="dealer">Dealer</option>
              <option value="vip">VIP</option>
            </select>
          </div>
          <div>
            <label className="admin-label" htmlFor="price-currency">
              Currency
            </label>
            <select
              id="price-currency"
              className="admin-input mt-1"
              value={form.currency}
              onChange={(event) =>
                setForm({ ...form, currency: event.target.value })
              }
            >
              <option value="TZS">TZS</option>
              <option value="USD">USD</option>
              <option value="KES">KES</option>
              <option value="EUR">EUR</option>
            </select>
          </div>
          <div>
            <label className="admin-label" htmlFor="price-amount">
              Amount *
            </label>
            <input
              id="price-amount"
              type="number"
              min="0"
              step="0.01"
              className="admin-input mt-1"
              value={form.amount}
              onChange={(event) =>
                setForm({ ...form, amount: event.target.value })
              }
            />
          </div>
          <div>
            <label className="admin-label" htmlFor="price-compare">
              Compare at
            </label>
            <input
              id="price-compare"
              type="number"
              min="0"
              step="0.01"
              className="admin-input mt-1"
              value={form.compareAtPrice}
              onChange={(event) =>
                setForm({ ...form, compareAtPrice: event.target.value })
              }
            />
          </div>
          <div>
            <label className="admin-label" htmlFor="price-cost">
              Cost
            </label>
            <input
              id="price-cost"
              type="number"
              min="0"
              step="0.01"
              className="admin-input mt-1"
              value={form.costPrice}
              onChange={(event) =>
                setForm({ ...form, costPrice: event.target.value })
              }
            />
          </div>
          <div>
            <label className="admin-label" htmlFor="price-min-qty">
              Minimum quantity
            </label>
            <input
              id="price-min-qty"
              type="number"
              min="1"
              step="1"
              className="admin-input mt-1"
              value={form.minimumQuantity}
              onChange={(event) =>
                setForm({ ...form, minimumQuantity: event.target.value })
              }
            />
          </div>
          <div>
            <label className="admin-label" htmlFor="price-starts">
              Starts at
            </label>
            <input
              id="price-starts"
              type="datetime-local"
              className="admin-input mt-1"
              value={form.startsAt}
              onChange={(event) =>
                setForm({ ...form, startsAt: event.target.value })
              }
            />
          </div>
          <div>
            <label className="admin-label" htmlFor="price-ends">
              Ends at
            </label>
            <input
              id="price-ends"
              type="datetime-local"
              className="admin-input mt-1"
              value={form.endsAt}
              onChange={(event) =>
                setForm({ ...form, endsAt: event.target.value })
              }
            />
          </div>
        </div>

        <label className="mt-3 flex items-center gap-2 text-sm text-zinc-700">
          <input
            type="checkbox"
            checked={form.isActive}
            onChange={(event) =>
              setForm({ ...form, isActive: event.target.checked })
            }
          />
          Active
        </label>

        <div className="mt-3">
          <button
            type="button"
            className="admin-btn-primary"
            disabled={busy}
            onClick={() => void handleSave()}
          >
            {busy ? "Saving…" : editingId ? "Update price" : "Create price"}
          </button>
        </div>
      </div>
    </div>
  );
}
