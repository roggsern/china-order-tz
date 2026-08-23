"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { AdminPageHeader } from "@/components/admin/AdminPageHeader";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";
import {
  AdminPaymentConfigApiError,
  PAYMENT_METHOD_LABELS,
  PAYMENT_METHOD_ORDER,
  canManagePaymentConfig,
  canViewPaymentConfig,
  defaultPaymentEnabledMethods,
  fetchAdminPaymentConfig,
  mergePaymentEnabledMethods,
  paymentEnabledMethodsPayload,
  updateAdminPaymentConfig,
  type PaymentEnabledMethods,
} from "@/lib/api/admin-payment-config";

export function AdminPaymentSettingsPanel() {
  const { permissions, loading: permissionsLoading } = useAdminPermissions();
  const canView = canViewPaymentConfig(permissions);
  const canManage = canManagePaymentConfig(permissions);

  const [defaultProvider, setDefaultProvider] = useState("nmb");
  const [methods, setMethods] = useState<PaymentEnabledMethods>(defaultPaymentEnabledMethods);
  const [providerStatus, setProviderStatus] = useState<
    Record<string, { enabled: boolean; available: boolean }>
  >({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const enabledChoices = useMemo(
    () => PAYMENT_METHOD_ORDER.filter((method) => methods[method]),
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
      setMethods(mergePaymentEnabledMethods(config.enabled_methods));
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
        const fallback = PAYMENT_METHOD_ORDER.find((key) => key !== method && next[key]);
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
        enabled_methods: paymentEnabledMethodsPayload(methods),
      });
      setDefaultProvider(updated.default_provider || "nmb");
      setMethods(mergePaymentEnabledMethods(updated.enabled_methods));
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
      <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <div className="admin-card p-6 text-sm text-zinc-600">Checking permissions…</div>
      </div>
    );
  }

  if (!canView) {
    return (
      <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <AdminPageHeader title="Payment configuration" />
        <div className="admin-card p-6">
          <p className="text-sm text-zinc-600">
            You need <code className="text-zinc-900">payments.config.view</code> to open this page.
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <AdminPageHeader
        title="Payment configuration"
        description="Control which payment methods are available and the default provider. API keys and merchant secrets stay in environment variables."
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
        <div className="admin-card p-6 text-sm text-zinc-600">Loading payment configuration…</div>
      ) : (
        <>
          <section className="admin-card p-5">
            <h2 className="text-lg font-semibold text-zinc-900">Default provider</h2>
            <p className="mt-1 text-sm text-zinc-600">
              Must be one of the enabled payment methods.
            </p>
            <label className="admin-label mt-4">
              Provider
              <select
                value={defaultProvider}
                disabled={!canManage || saving || enabledChoices.length === 0}
                onChange={(event) => {
                  setDefaultProvider(event.target.value);
                  setSuccess(null);
                }}
                className="admin-input mt-1 max-w-md disabled:opacity-60"
              >
                {enabledChoices.map((method) => (
                  <option key={method} value={method}>
                    {PAYMENT_METHOD_LABELS[method]}
                  </option>
                ))}
              </select>
            </label>
          </section>

          <section className="admin-card p-5">
            <h2 className="text-lg font-semibold text-zinc-900">Enabled methods</h2>
            <p className="mt-1 text-sm text-zinc-600">
              Disabled methods cannot be selected as the default provider.
            </p>
            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {PAYMENT_METHOD_ORDER.map((method) => {
                const status = providerStatus[method];
                return (
                  <label
                    key={method}
                    className="flex items-start gap-3 rounded-xl border border-zinc-200 bg-zinc-50/80 px-3 py-3"
                  >
                    <input
                      type="checkbox"
                      checked={Boolean(methods[method])}
                      disabled={!canManage || saving}
                      onChange={(event) => toggleMethod(method, event.target.checked)}
                      className="mt-1 h-4 w-4 rounded border-zinc-300"
                    />
                    <span>
                      <span className="block text-sm font-medium text-zinc-900">
                        {PAYMENT_METHOD_LABELS[method]}
                      </span>
                      <span className="mt-0.5 block text-xs text-zinc-600">
                        {status?.available
                          ? "Provider available"
                          : method === "nmb"
                            ? "NMB credentials not configured in ENV"
                            : method === "snippe"
                              ? "Snippe credentials not configured in ENV"
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
              className="admin-btn-primary disabled:cursor-not-allowed disabled:opacity-50"
            >
              {saving ? "Saving…" : canManage ? "Save configuration" : "View only"}
            </button>
          </div>
        </>
      )}
    </div>
  );
}
