"use client";

import { useCallback, useEffect, useState } from "react";
import { AdminEmptyState } from "@/components/admin/AdminEmptyState";
import { AdminPageHeader } from "@/components/admin/AdminPageHeader";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";
import {
  AdminCatalogApiError,
  fetchAdminStores,
  type AdminStoreOption,
} from "@/lib/api/admin-catalog";
import {
  AdminStoreSettingsApiError,
  canManageStoreSettings,
  canViewStoreSettings,
  emptyStoreSettingsSections,
  fetchAdminStoreSettings,
  updateAdminStoreSettings,
  type AdminStoreSettings,
} from "@/lib/api/admin-store-settings";

export function AdminStoreSettingsPanel() {
  const { permissions, loading: permissionsLoading } = useAdminPermissions();
  const canView = canViewStoreSettings(permissions);
  const canManage = canManageStoreSettings(permissions);

  const [stores, setStores] = useState<AdminStoreOption[]>([]);
  const [storeId, setStoreId] = useState("");
  const [settings, setSettings] = useState<AdminStoreSettings | null>(null);
  const [loadingStores, setLoadingStores] = useState(true);
  const [loadingSettings, setLoadingSettings] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const loadStores = useCallback(async () => {
    if (!canView) {
      setLoadingStores(false);
      return;
    }

    setLoadingStores(true);
    setError(null);
    try {
      const rows = await fetchAdminStores();
      setStores(rows);
      setStoreId((current) => current || rows[0]?.id || "");
    } catch (err) {
      setStores([]);
      setError(
        err instanceof AdminCatalogApiError ? err.message : "Unable to load stores.",
      );
    } finally {
      setLoadingStores(false);
    }
  }, [canView]);

  const loadSettings = useCallback(async (id: string) => {
    if (!id) {
      setSettings(null);
      return;
    }

    setLoadingSettings(true);
    setError(null);
    try {
      const data = await fetchAdminStoreSettings(id);
      setSettings(data);
    } catch (err) {
      setSettings(null);
      setError(
        err instanceof AdminStoreSettingsApiError
          ? err.message
          : "Unable to load store settings.",
      );
    } finally {
      setLoadingSettings(false);
    }
  }, []);

  useEffect(() => {
    if (permissionsLoading) {
      return;
    }
    void loadStores();
  }, [permissionsLoading, loadStores]);

  useEffect(() => {
    if (!storeId || !canView) {
      return;
    }
    void loadSettings(storeId);
  }, [storeId, canView, loadSettings]);

  const sections = settings ?? {
    store_id: storeId,
    store_code: "",
    store_name: "",
    ...emptyStoreSettingsSections(),
  };

  const save = async () => {
    if (!canManage || !storeId) {
      return;
    }

    setSaving(true);
    setError(null);
    setSuccess(null);
    try {
      const updated = await updateAdminStoreSettings(storeId, {
        business: sections.business,
        receipt: sections.receipt,
        customer: sections.customer,
        social: sections.social,
      });
      setSettings(updated);
      setSuccess("Store settings saved.");
    } catch (err) {
      setError(
        err instanceof AdminStoreSettingsApiError
          ? err.message
          : "Unable to update store settings.",
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
        <AdminPageHeader title="Store settings" />
        <div className="admin-card p-6">
          <p className="text-sm text-zinc-600">
            You need <code className="text-zinc-900">stores.view</code> to open this page.
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <AdminPageHeader
        title="Store settings"
        description="Manage business information, customer contact, receipt content, and social links. Stored in each store&apos;s settings JSON — secrets stay out of the database."
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

      <section className="admin-card p-5">
        <label className="admin-label">
          Store
          <select
            value={storeId}
            disabled={loadingStores || saving}
            onChange={(event) => {
              setStoreId(event.target.value);
              setSuccess(null);
            }}
            className="admin-input mt-1 max-w-md disabled:opacity-60"
          >
            {stores.length === 0 ? <option value="">No stores available</option> : null}
            {stores.map((store) => (
              <option key={store.id} value={store.id}>
                {store.name} ({store.code})
              </option>
            ))}
          </select>
        </label>
        {storeId ? (
          <p className="mt-3 text-sm text-zinc-600">
            <a
              href={`/admin/categories?origin=tz&store_id=${encodeURIComponent(storeId)}`}
              className="font-medium text-[#8b6914] underline-offset-2 hover:underline"
            >
              Manage store categories →
            </a>
            <span className="text-zinc-500">
              {" "}
              (TZ_LOCAL catalog for the selected store)
            </span>
          </p>
        ) : null}
      </section>

      {loadingStores || loadingSettings ? (
        <div className="admin-card p-6 text-sm text-zinc-600">Loading store settings…</div>
      ) : !storeId ? (
        <AdminEmptyState
          title="Select a store to edit settings"
          description="Choose a store above to load business, contact, receipt, and social settings."
        />
      ) : (
        <>
          <section className="admin-card p-5">
            <h2 className="text-lg font-semibold text-zinc-900">Business information</h2>
            <div className="mt-4 grid gap-3 md:grid-cols-2">
              {(
                [
                  ["display_name", "Display name"],
                  ["phone", "Phone"],
                  ["email", "Email"],
                  ["address", "Address"],
                ] as const
              ).map(([key, label]) => (
                <label key={key} className="admin-label md:col-span-1">
                  {label}
                  <input
                    type={key === "email" ? "email" : "text"}
                    value={sections.business[key]}
                    disabled={!canManage || saving}
                    onChange={(event) => {
                      setSettings((prev) =>
                        prev
                          ? {
                              ...prev,
                              business: { ...prev.business, [key]: event.target.value },
                            }
                          : prev,
                      );
                      setSuccess(null);
                    }}
                    className="admin-input mt-1 disabled:opacity-60"
                  />
                </label>
              ))}
            </div>
          </section>

          <section className="admin-card p-5">
            <h2 className="text-lg font-semibold text-zinc-900">Customer contact</h2>
            <div className="mt-4 grid gap-3 md:grid-cols-2">
              <label className="admin-label">
                Support phone
                <input
                  type="text"
                  value={sections.customer.support_phone}
                  disabled={!canManage || saving}
                  onChange={(event) => {
                    setSettings((prev) =>
                      prev
                        ? {
                            ...prev,
                            customer: { ...prev.customer, support_phone: event.target.value },
                          }
                        : prev,
                    );
                    setSuccess(null);
                  }}
                  className="admin-input mt-1 disabled:opacity-60"
                />
              </label>
              <label className="admin-label">
                Support email
                <input
                  type="email"
                  value={sections.customer.support_email}
                  disabled={!canManage || saving}
                  onChange={(event) => {
                    setSettings((prev) =>
                      prev
                        ? {
                            ...prev,
                            customer: { ...prev.customer, support_email: event.target.value },
                          }
                        : prev,
                    );
                    setSuccess(null);
                  }}
                  className="admin-input mt-1 disabled:opacity-60"
                />
              </label>
            </div>
          </section>

          <section className="admin-card p-5">
            <h2 className="text-lg font-semibold text-zinc-900">Receipt settings</h2>
            <p className="mt-1 text-sm text-zinc-600">
              Updates merge into existing receipt JSON used by POS — other receipt keys are preserved.
            </p>
            <div className="mt-4 space-y-3">
              <label className="admin-label">
                Footer message
                <textarea
                  value={sections.receipt.footer_message}
                  disabled={!canManage || saving}
                  rows={3}
                  onChange={(event) => {
                    setSettings((prev) =>
                      prev
                        ? {
                            ...prev,
                            receipt: { ...prev.receipt, footer_message: event.target.value },
                          }
                        : prev,
                    );
                    setSuccess(null);
                  }}
                  className="admin-input mt-1 disabled:opacity-60"
                />
              </label>
              <label className="flex items-center gap-3 text-sm text-zinc-600">
                <input
                  type="checkbox"
                  checked={Boolean(sections.receipt.show_logo)}
                  disabled={!canManage || saving}
                  onChange={(event) => {
                    setSettings((prev) =>
                      prev
                        ? {
                            ...prev,
                            receipt: { ...prev.receipt, show_logo: event.target.checked },
                          }
                        : prev,
                    );
                    setSuccess(null);
                  }}
                  className="h-4 w-4 rounded border-zinc-300"
                />
                Show logo on receipt
              </label>
            </div>
          </section>

          <section className="admin-card p-5">
            <h2 className="text-lg font-semibold text-zinc-900">Social links</h2>
            <div className="mt-4 grid gap-3 md:grid-cols-3">
              {(
                [
                  ["instagram", "Instagram"],
                  ["facebook", "Facebook"],
                  ["tiktok", "TikTok"],
                ] as const
              ).map(([key, label]) => (
                <label key={key} className="admin-label">
                  {label}
                  <input
                    type="text"
                    value={sections.social[key]}
                    disabled={!canManage || saving}
                    onChange={(event) => {
                      setSettings((prev) =>
                        prev
                          ? {
                              ...prev,
                              social: { ...prev.social, [key]: event.target.value },
                            }
                          : prev,
                      );
                      setSuccess(null);
                    }}
                    className="admin-input mt-1 disabled:opacity-60"
                  />
                </label>
              ))}
            </div>
          </section>

          <div>
            <button
              type="button"
              disabled={!canManage || saving || !storeId}
              onClick={() => void save()}
              className="admin-btn-primary disabled:cursor-not-allowed disabled:opacity-50"
            >
              {saving ? "Saving…" : canManage ? "Save settings" : "View only"}
            </button>
          </div>
        </>
      )}
    </div>
  );
}
