"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";
import {
  AdminPaymentConfigApiError,
  PAYMENT_METHOD_LABELS,
  canManagePaymentConfig,
  canViewPaymentConfig,
  fetchAdminPaymentConfig,
  updateAdminPaymentConfig,
  type PaymentEnabledMethods,
} from "@/lib/api/admin-payment-config";

const METHOD_ORDER: (keyof PaymentEnabledMethods)[] = [
  "nmb",
  "mpesa",
  "card",
  "cash",
  "bank_transfer",
];

function defaultMethods(): PaymentEnabledMethods {
  return {
    nmb: true,
    mpesa: false,
    card: false,
    cash: false,
    bank_transfer: false,
  };
}

export function AdminPaymentSettingsPanel() {
  const { permissions, loading: permissionsLoading } = useAdminPermissions();
  const canView = canViewPaymentConfig(permissions);
  const canManage = canManagePaymentConfig(permissions);

  const [defaultProvider, setDefaultProvider] = useState("nmb");
  const [methods, setMethods] = useState<PaymentEnabledMethods>(defaultMethods);
  const [providerStatus, setProviderStatus] = useState<
    Record<string, { enabled: boolean; available: boolean }>
  >({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const enabledChoices = useMemo(
    () => METHOD_ORDER.filter((method) => methods[method]),
    [methods],
  );

  const reload = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const config = await fetchAdminPaymentConfig();
      setDefaultProvider(config.default_provider || "nmb");
      setMethods({ ...defaultMethods(), ...config.enabled_methods });
      setProviderStatus(config.provider_status ?? {});
    } catch (err) {
      setError(
        err instanceof AdminPaymentConfigApiError
          ? err.message
          : "Unable to load payment configuration.",
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

  const toggleMethod = (method: keyof PaymentEnabledMethods, enabled: boolean) => {
    setMethods((prev) => {
      const next = { ...prev, [method]: enabled };
      if (!enabled && defaultProvider === method) {
        const fallback = METHOD_ORDER.find((key) => key !== method && next[key]);
        if (fallback) {
          setDefaultProvider(fallback);
        }
      }
      return next;
    });
    setSuccess(null);
  };

  const save = async () => {
    if (!canManage) {
      return;
    }

    setSaving(true);
    setError(null);
    setSuccess(null);
    try {
      const updated = await updateAdminPaymentConfig({
        default_provider: defaultProvider,
        enabled_methods: methods,
      });
      setDefaultProvider(updated.default_provider || "nmb");
      setMethods({ ...defaultMethods(), ...updated.enabled_methods });
      setProviderStatus(updated.provider_status ?? {});
      setSuccess("Payment configuration saved.");
    } catch (err) {
      setError(
        err instanceof AdminPaymentConfigApiError
          ? err.message
          : "Unable to update payment configuration.",
      );
    } finally {
      setSaving(false);
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
        <h1 className="text-xl font-semibold text-zinc-100">Payment configuration</h1>
        <p className="mt-2 text-sm text-zinc-400">
          You need <code className="text-zinc-300">payments.config.view</code> to open this page.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-zinc-100">Payment configuration</h1>
        <p className="mt-1 text-sm text-zinc-400">
          Control which payment methods are available and the default provider. API keys and merchant
          secrets stay in environment variables.
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
          Loading payment configuration…
        </div>
      ) : (
        <>
          <section className="rounded-xl border border-zinc-800 bg-zinc-950/50 p-5">
            <h2 className="text-lg font-medium text-zinc-100">Default provider</h2>
            <p className="mt-1 text-sm text-zinc-500">
              Must be one of the enabled payment methods.
            </p>
            <select
              value={defaultProvider}
              disabled={!canManage || saving || enabledChoices.length === 0}
              onChange={(event) => {
                setDefaultProvider(event.target.value);
                setSuccess(null);
              }}
              className="mt-4 w-full max-w-md rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100 disabled:opacity-60"
            >
              {enabledChoices.map((method) => (
                <option key={method} value={method}>
                  {PAYMENT_METHOD_LABELS[method]}
                </option>
              ))}
            </select>
          </section>

          <section className="rounded-xl border border-zinc-800 bg-zinc-950/50 p-5">
            <h2 className="text-lg font-medium text-zinc-100">Enabled methods</h2>
            <p className="mt-1 text-sm text-zinc-500">
              Disabled methods cannot be selected as the default provider.
            </p>
            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {METHOD_ORDER.map((method) => {
                const status = providerStatus[method];
                return (
                  <label
                    key={method}
                    className="flex items-start gap-3 rounded-lg border border-zinc-800 bg-zinc-900/50 px-3 py-3"
                  >
                    <input
                      type="checkbox"
                      checked={Boolean(methods[method])}
                      disabled={!canManage || saving}
                      onChange={(event) => toggleMethod(method, event.target.checked)}
                      className="mt-1 h-4 w-4 rounded border-zinc-600 bg-zinc-900"
                    />
                    <span>
                      <span className="block text-sm font-medium text-zinc-100">
                        {PAYMENT_METHOD_LABELS[method]}
                      </span>
                      <span className="mt-0.5 block text-xs text-zinc-500">
                        {status?.available
                          ? "Provider available"
                          : method === "nmb"
                            ? "NMB credentials not configured in ENV"
                            : method === "cash" || method === "bank_transfer"
                              ? "Local method"
                              : "External provider not configured"}
                      </span>
                    </span>
                  </label>
                );
              })}
            </div>
          </section>

          <div>
            <button
              type="button"
              disabled={!canManage || saving}
              onClick={() => void save()}
              className="rounded-lg bg-[#e8c547] px-4 py-2 text-sm font-medium text-zinc-950 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {saving ? "Saving…" : canManage ? "Save configuration" : "View only"}
            </button>
          </div>
        </>
      )}
    </div>
  );
}
