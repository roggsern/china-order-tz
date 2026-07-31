"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
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
      <div className="rounded-xl border border-zinc-800 bg-zinc-950/40 p-6 text-sm text-zinc-400">
        Checking permissions…
      </div>
    );
  }

  if (!canView) {
    return (
      <div className="rounded-xl border border-zinc-800 bg-zinc-950/40 p-6">
        <h1 className="text-xl font-semibold text-zinc-100">Shipping configuration</h1>
        <p className="mt-2 text-sm text-zinc-400">
          You need <code className="text-zinc-300">shipping.view</code> to open this page.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-zinc-100">Shipping configuration</h1>
        <p className="mt-1 text-sm text-zinc-400">
          Manage air freight, sea freight, and local delivery prices and duration windows.
          Rates are stored in <code className="text-zinc-300">shipping_rates</code>.
        </p>
      </div>

      {error ? (
        <div className="rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-200">
          {error}
        </div>
      ) : null}
      {success ? (
        <div className="rounded-lg border border-emerald-900/60 bg-emerald-950/30 px-4 py-3 text-sm text-emerald-200">
          {success}
        </div>
      ) : null}

      {loading ? (
        <div className="rounded-xl border border-zinc-800 bg-zinc-950/40 p-6 text-sm text-zinc-400">
          Loading shipping rates…
        </div>
      ) : (
        <div className="grid gap-4 lg:grid-cols-3">
          {orderedRates.map((rate) => {
            const form = forms[rate.method] ?? toForm(rate);
            const busy = savingMethod === rate.method;

            return (
              <section
                key={rate.method}
                className="rounded-xl border border-zinc-800 bg-zinc-950/50 p-5"
              >
                <div className="mb-4 flex items-start justify-between gap-3">
                  <div>
                    <h2 className="text-lg font-medium text-zinc-100">{methodLabel(rate)}</h2>
                    <p className="text-xs text-zinc-500">{rate.method}</p>
                  </div>
                  <label className="flex items-center gap-2 text-sm text-zinc-300">
                    <input
                      type="checkbox"
                      checked={form.active}
                      disabled={!canManage || busy}
                      onChange={(event) =>
                        updateField(rate.method, "active", event.target.checked)
                      }
                      className="h-4 w-4 rounded border-zinc-600 bg-zinc-900"
                    />
                    Active
                  </label>
                </div>

                <div className="space-y-3">
                  <label className="block text-sm text-zinc-300">
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
                      className="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100 disabled:opacity-60"
                    />
                  </label>

                  <div className="grid grid-cols-3 gap-2">
                    <label className="block text-sm text-zinc-300">
                      Min days
                      <input
                        type="number"
                        min={0}
                        value={form.estimated_min_days}
                        disabled={!canManage || busy}
                        onChange={(event) =>
                          updateField(rate.method, "estimated_min_days", event.target.value)
                        }
                        className="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100 disabled:opacity-60"
                      />
                    </label>
                    <label className="block text-sm text-zinc-300">
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
                        className="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100 disabled:opacity-60"
                      />
                    </label>
                    <label className="block text-sm text-zinc-300">
                      Max days
                      <input
                        type="number"
                        min={0}
                        value={form.estimated_max_days}
                        disabled={!canManage || busy}
                        onChange={(event) =>
                          updateField(rate.method, "estimated_max_days", event.target.value)
                        }
                        className="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100 disabled:opacity-60"
                      />
                    </label>
                  </div>
                </div>

                <div className="mt-5">
                  <button
                    type="button"
                    disabled={!canManage || busy}
                    onClick={() => void save(rate.method)}
                    className="w-full rounded-lg bg-[#e8c547] px-3 py-2 text-sm font-medium text-zinc-950 disabled:cursor-not-allowed disabled:opacity-50"
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
