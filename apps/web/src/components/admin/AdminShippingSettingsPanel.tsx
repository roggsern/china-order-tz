"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { AdminEmptyState } from "@/components/admin/AdminEmptyState";
import { AdminPageHeader } from "@/components/admin/AdminPageHeader";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";
import {
  AdminShippingRatesApiError,
  SHIPPING_METHOD_LABELS,
  canManageShippingRates,
  canViewShippingRates,
  fetchAdminShippingRates,
  updateAdminShippingRate,
  type AdminShippingRate,
} from "@/lib/api/admin-shipping-rates";

type RateFormState = {
  price: string;
  estimated_min_days: string;
  estimated_max_days: string;
  estimated_delivery_days: string;
  active: boolean;
};

function toForm(rate: AdminShippingRate): RateFormState {
  return {
    price: String(rate.price ?? 0),
    estimated_min_days: rate.estimated_min_days != null ? String(rate.estimated_min_days) : "",
    estimated_max_days: rate.estimated_max_days != null ? String(rate.estimated_max_days) : "",
    estimated_delivery_days:
      rate.estimated_delivery_days != null ? String(rate.estimated_delivery_days) : "",
    active: Boolean(rate.active),
  };
}

function methodLabel(rate: AdminShippingRate): string {
  return rate.method_name || SHIPPING_METHOD_LABELS[rate.method] || rate.method;
}

export function AdminShippingSettingsPanel() {
  const { permissions, loading: permissionsLoading } = useAdminPermissions();
  const canView = canViewShippingRates(permissions);
  const canManage = canManageShippingRates(permissions);

  const [rates, setRates] = useState<AdminShippingRate[]>([]);
  const [forms, setForms] = useState<Record<string, RateFormState>>({});
  const [loading, setLoading] = useState(true);
  const [savingMethod, setSavingMethod] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const orderedRates = useMemo(() => {
    const order = ["air_freight", "sea_freight", "local_delivery"];
    return [...rates].sort(
      (a, b) => order.indexOf(a.method) - order.indexOf(b.method),
    );
  }, [rates]);

  const reload = useCallback(async () => {
    if (!canView) {
      setRates([]);
      setForms({});
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const rows = await fetchAdminShippingRates();
      setRates(rows);
      const nextForms: Record<string, RateFormState> = {};
      for (const row of rows) {
        nextForms[row.method] = toForm(row);
      }
      setForms(nextForms);
    } catch (err) {
      setRates([]);
      setForms({});
      setError(
        err instanceof AdminShippingRatesApiError
          ? err.message
          : "Unable to load shipping rates.",
      );
    } finally {
      setLoading(false);
    }
  }, [canView]);

  useEffect(() => {
    if (permissionsLoading) {
      return;
    }
    void reload();
  }, [permissionsLoading, reload]);

  const updateField = (
    method: string,
    field: keyof RateFormState,
    value: string | boolean,
  ) => {
    setForms((prev) => ({
      ...prev,
      [method]: {
        ...(prev[method] ?? {
          price: "0",
          estimated_min_days: "",
          estimated_max_days: "",
          estimated_delivery_days: "",
          active: false,
        }),
        [field]: value,
      },
    }));
    setSuccess(null);
  };

  const save = async (method: string) => {
    if (!canManage) {
      return;
    }

    const form = forms[method];
    if (!form) {
      return;
    }

    setSavingMethod(method);
    setError(null);
    setSuccess(null);

    try {
      const updated = await updateAdminShippingRate(method, {
        price: Number(form.price),
        estimated_min_days: Number(form.estimated_min_days),
        estimated_max_days: Number(form.estimated_max_days),
        estimated_delivery_days: Number(form.estimated_delivery_days),
        active: form.active,
      });

      setRates((prev) =>
        prev.map((row) => (row.method === method ? updated : row)),
      );
      setForms((prev) => ({ ...prev, [method]: toForm(updated) }));
      setSuccess(`${methodLabel(updated)} updated.`);
    } catch (err) {
      setError(
        err instanceof AdminShippingRatesApiError
          ? err.message
          : "Unable to update shipping rate.",
      );
    } finally {
      setSavingMethod(null);
    }
  };

  if (permissionsLoading) {
    return (
      <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <div className="admin-card p-6 text-sm text-zinc-600">Checking permissions…</div>
      </div>
    );
  }

  if (!canView) {
    return (
      <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <AdminPageHeader title="Shipping configuration" />
        <div className="admin-card p-6">
          <p className="text-sm text-zinc-600">
            You need <code className="text-zinc-900">shipping.view</code> to open this page.
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <AdminPageHeader
        title="Shipping configuration"
        description="Manage air freight, sea freight, and local delivery prices and duration windows. Rates are stored in shipping_rates."
      />

      {error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      ) : null}
      {success ? (
        <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
          {success}
        </div>
      ) : null}

      {loading ? (
        <div className="admin-card p-6 text-sm text-zinc-600">Loading shipping rates…</div>
      ) : orderedRates.length === 0 ? (
        <AdminEmptyState title="No shipping rates configured" />
      ) : (
        <div className="grid gap-4 lg:grid-cols-3">
          {orderedRates.map((rate) => {
            const form = forms[rate.method] ?? toForm(rate);
            const busy = savingMethod === rate.method;

            return (
              <section key={rate.method} className="admin-card p-5">
                <div className="mb-4 flex items-start justify-between gap-3">
                  <div>
                    <h2 className="text-lg font-semibold text-zinc-900">{methodLabel(rate)}</h2>
                    <p className="text-xs text-zinc-600">{rate.method}</p>
                  </div>
                  <label className="flex items-center gap-2 text-sm text-zinc-600">
                    <input
                      type="checkbox"
                      checked={form.active}
                      disabled={!canManage || busy}
                      onChange={(event) =>
                        updateField(rate.method, "active", event.target.checked)
                      }
                      className="h-4 w-4 rounded border-zinc-300"
                    />
                    Active
                  </label>
                </div>

                <div className="space-y-3">
                  <label className="admin-label">
                    Price ({rate.currency || "TZS"})
                    <input
                      type="number"
                      min={0}
                      step="0.01"
                      value={form.price}
                      disabled={!canManage || busy}
                      onChange={(event) =>
                        updateField(rate.method, "price", event.target.value)
                      }
                      className="admin-input mt-1 disabled:opacity-60"
                    />
                  </label>

                  <div className="grid grid-cols-3 gap-2">
                    <label className="admin-label">
                      Min days
                      <input
                        type="number"
                        min={0}
                        value={form.estimated_min_days}
                        disabled={!canManage || busy}
                        onChange={(event) =>
                          updateField(rate.method, "estimated_min_days", event.target.value)
                        }
                        className="admin-input mt-1 disabled:opacity-60"
                      />
                    </label>
                    <label className="admin-label">
                      Typical
                      <input
                        type="number"
                        min={0}
                        value={form.estimated_delivery_days}
                        disabled={!canManage || busy}
                        onChange={(event) =>
                          updateField(
                            rate.method,
                            "estimated_delivery_days",
                            event.target.value,
                          )
                        }
                        className="admin-input mt-1 disabled:opacity-60"
                      />
                    </label>
                    <label className="admin-label">
                      Max days
                      <input
                        type="number"
                        min={0}
                        value={form.estimated_max_days}
                        disabled={!canManage || busy}
                        onChange={(event) =>
                          updateField(rate.method, "estimated_max_days", event.target.value)
                        }
                        className="admin-input mt-1 disabled:opacity-60"
                      />
                    </label>
                  </div>
                </div>

                <div className="mt-5">
                  <button
                    type="button"
                    disabled={!canManage || busy}
                    onClick={() => void save(rate.method)}
                    className="admin-btn-primary w-full disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    {busy ? "Saving…" : canManage ? "Save changes" : "View only"}
                  </button>
                </div>
              </section>
            );
          })}
        </div>
      )}
    </div>
  );
}
