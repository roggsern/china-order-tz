"use client";

import { useCallback, useEffect, useState } from "react";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";
import {
  AdminFeatureConfigApiError,
  FEATURE_FLAG_LABELS,
  canManageFeatureConfig,
  canViewFeatureConfig,
  fetchAdminFeatureConfig,
  updateAdminFeatureConfig,
  type FeatureFlags,
} from "@/lib/api/admin-feature-config";

const FLAG_ORDER: (keyof FeatureFlags)[] = ["wishlist", "reviews", "new_checkout"];

function defaultFlags(): FeatureFlags {
  return {
    wishlist: false,
    reviews: false,
    new_checkout: false,
  };
}

export function AdminFeatureSettingsPanel() {
  const { permissions, loading: permissionsLoading } = useAdminPermissions();
  const canView = canViewFeatureConfig(permissions);
  const canManage = canManageFeatureConfig(permissions);

  const [maintenanceMode, setMaintenanceMode] = useState(false);
  const [maintenanceMessage, setMaintenanceMessage] = useState("");
  const [flags, setFlags] = useState<FeatureFlags>(defaultFlags);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const reload = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const config = await fetchAdminFeatureConfig();
      setMaintenanceMode(Boolean(config.maintenance_mode));
      setMaintenanceMessage(config.maintenance_message ?? "");
      setFlags({ ...defaultFlags(), ...config.flags });
    } catch (err) {
      setError(
        err instanceof AdminFeatureConfigApiError
          ? err.message
          : "Unable to load feature configuration.",
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

  const save = async () => {
    if (!canManage) {
      return;
    }

    setSaving(true);
    setError(null);
    setSuccess(null);
    try {
      const updated = await updateAdminFeatureConfig({
        maintenance_mode: maintenanceMode,
        maintenance_message: maintenanceMessage,
        flags,
      });
      setMaintenanceMode(Boolean(updated.maintenance_mode));
      setMaintenanceMessage(updated.maintenance_message ?? "");
      setFlags({ ...defaultFlags(), ...updated.flags });
      setSuccess("Feature configuration saved.");
    } catch (err) {
      setError(
        err instanceof AdminFeatureConfigApiError
          ? err.message
          : "Unable to update feature configuration.",
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
        <h1 className="text-xl font-semibold text-zinc-100">Feature configuration</h1>
        <p className="mt-2 text-sm text-zinc-400">
          You need <code className="text-zinc-300">features.view</code> to open this page.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-zinc-100">Feature configuration</h1>
        <p className="mt-1 text-sm text-zinc-400">
          Toggle optional product features and maintenance mode. Flags cannot control payments,
          inventory, permissions, or order lifecycle.
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
          Loading feature configuration…
        </div>
      ) : (
        <>
          <section className="rounded-xl border border-zinc-800 bg-zinc-950/50 p-5">
            <h2 className="text-lg font-medium text-zinc-100">Maintenance mode</h2>
            <label className="mt-4 flex items-center gap-3 text-sm text-zinc-300">
              <input
                type="checkbox"
                checked={maintenanceMode}
                disabled={!canManage || saving}
                onChange={(event) => {
                  setMaintenanceMode(event.target.checked);
                  setSuccess(null);
                }}
                className="h-4 w-4 rounded border-zinc-600 bg-zinc-900"
              />
              Enable maintenance mode
            </label>
            <label className="mt-4 block text-sm text-zinc-300">
              Maintenance message
              <textarea
                value={maintenanceMessage}
                disabled={!canManage || saving}
                onChange={(event) => {
                  setMaintenanceMessage(event.target.value);
                  setSuccess(null);
                }}
                rows={3}
                className="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-zinc-100 disabled:opacity-60"
                placeholder="Optional message shown while maintenance is on"
              />
            </label>
          </section>

          <section className="rounded-xl border border-zinc-800 bg-zinc-950/50 p-5">
            <h2 className="text-lg font-medium text-zinc-100">Feature flags</h2>
            <p className="mt-1 text-sm text-zinc-500">
              Optional UX features only. Core commerce and security paths stay unchanged.
            </p>
            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {FLAG_ORDER.map((flag) => (
                <label
                  key={flag}
                  className="flex items-start gap-3 rounded-lg border border-zinc-800 bg-zinc-900/50 px-3 py-3"
                >
                  <input
                    type="checkbox"
                    checked={Boolean(flags[flag])}
                    disabled={!canManage || saving}
                    onChange={(event) => {
                      setFlags((prev) => ({ ...prev, [flag]: event.target.checked }));
                      setSuccess(null);
                    }}
                    className="mt-1 h-4 w-4 rounded border-zinc-600 bg-zinc-900"
                  />
                  <span>
                    <span className="block text-sm font-medium text-zinc-100">
                      {FEATURE_FLAG_LABELS[flag]}
                    </span>
                    <span className="mt-0.5 block text-xs text-zinc-500">{flag}</span>
                  </span>
                </label>
              ))}
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
