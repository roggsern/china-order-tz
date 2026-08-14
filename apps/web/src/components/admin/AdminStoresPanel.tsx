"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
import { AdminEmptyState } from "@/components/admin/AdminEmptyState";
import { AdminPageHeader } from "@/components/admin/AdminPageHeader";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";
import {
  brandingUploadReady,
  canCreateStores,
  canUpdateStores,
  canViewStores,
  mapAdminStoreFormValues,
  mapAdminStoreListItem,
  toCreateStorePayload,
  toUpdateStorePayload,
  type AdminStoreFormValues,
  type AdminStoreListItemView,
} from "@/lib/admin/admin-stores";
import {
  AdminStoresApiError,
  createAdminStore,
  fetchAdminStore,
  fetchAdminStoreList,
  updateAdminStore,
  updateAdminStoreStatus,
  uploadAdminStoreBranding,
  type AdminStoreRecord,
} from "@/lib/api/admin-stores";
import { AdminStoreTeamPanel } from "@/components/admin/AdminStoreTeamPanel";

type Mode = "list" | "create" | "edit";
type EditTab = "overview" | "branding" | "settings" | "team" | "activity";

type ActivityRow = {
  id: string;
  event_type?: string;
  description?: string | null;
  created_at?: string | null;
  actor?: { name?: string | null } | null;
};

const emptyForm = (): AdminStoreFormValues => mapAdminStoreFormValues(null);

export function AdminStoresPanel({ initialStoreId }: { initialStoreId?: string }) {
  const { permissions, loading: permissionsLoading } = useAdminPermissions();
  const canView = canViewStores(permissions);
  const canCreate = canCreateStores(permissions);
  const canUpdate = canUpdateStores(permissions);

  const [mode, setMode] = useState<Mode>(initialStoreId ? "edit" : "list");
  const [tab, setTab] = useState<EditTab>("overview");
  const [rows, setRows] = useState<AdminStoreListItemView[]>([]);
  const [selected, setSelected] = useState<AdminStoreRecord | null>(null);
  const [form, setForm] = useState<AdminStoreFormValues>(emptyForm());
  const [logoFile, setLogoFile] = useState<File | null>(null);
  const [bannerFile, setBannerFile] = useState<File | null>(null);
  const [activity, setActivity] = useState<ActivityRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const reloadList = useCallback(async () => {
    if (!canView) {
      setRows([]);
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const stores = await fetchAdminStoreList();
      setRows(stores.map(mapAdminStoreListItem));
    } catch (err) {
      setError(err instanceof AdminStoresApiError ? err.message : "Unable to load stores.");
    } finally {
      setLoading(false);
    }
  }, [canView]);

  const openEdit = useCallback(async (id: string) => {
    setMode("edit");
    setTab("overview");
    setLoading(true);
    setError(null);
    setLogoFile(null);
    setBannerFile(null);
    try {
      const store = await fetchAdminStore(id);
      setSelected(store);
      setForm(mapAdminStoreFormValues(store));
    } catch (err) {
      setSelected(null);
      setError(err instanceof AdminStoresApiError ? err.message : "Unable to load store.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (permissionsLoading) return;
    if (initialStoreId && canView) {
      void openEdit(initialStoreId);
      return;
    }
    void reloadList();
  }, [permissionsLoading, canView, initialStoreId, openEdit, reloadList]);

  useEffect(() => {
    if (mode !== "edit" || tab !== "activity" || !selected) {
      return;
    }
    let cancelled = false;
    void (async () => {
      try {
        const params = new URLSearchParams({
          subject_type: "App\\Models\\Store",
          subject_id: selected.id,
          per_page: "20",
        });
        const response = await fetch(`/api/admin/activity-logs?${params.toString()}`, {
          credentials: "include",
          cache: "no-store",
        });
        const payload = (await response.json()) as { data?: ActivityRow[] };
        if (!cancelled) {
          setActivity(Array.isArray(payload.data) ? payload.data : []);
        }
      } catch {
        if (!cancelled) setActivity([]);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [mode, tab, selected]);

  const title = useMemo(() => {
    if (mode === "create") return "Create store";
    if (mode === "edit") return selected?.name ? `Edit ${selected.name}` : "Edit store";
    return "Stores";
  }, [mode, selected]);

  async function handleCreateSubmit(event: React.FormEvent) {
    event.preventDefault();
    if (!canCreate) return;
    setSaving(true);
    setError(null);
    try {
      const created = await createAdminStore(toCreateStorePayload(form));
      if (brandingUploadReady({ logo: logoFile, banner: bannerFile })) {
        await uploadAdminStoreBranding(created.id, { logo: logoFile, banner: bannerFile });
      }
      await openEdit(created.id);
      await reloadList();
    } catch (err) {
      setError(err instanceof AdminStoresApiError ? err.message : "Unable to create store.");
    } finally {
      setSaving(false);
    }
  }

  async function handleUpdateSubmit(event: React.FormEvent) {
    event.preventDefault();
    if (!canUpdate || !selected) return;
    setSaving(true);
    setError(null);
    try {
      const updated = await updateAdminStore(selected.id, toUpdateStorePayload(form));
      setSelected(updated);
      setForm(mapAdminStoreFormValues(updated));
      await reloadList();
    } catch (err) {
      setError(err instanceof AdminStoresApiError ? err.message : "Unable to update store.");
    } finally {
      setSaving(false);
    }
  }

  async function handleStatusToggle() {
    if (!canUpdate || !selected) return;
    setSaving(true);
    setError(null);
    try {
      const updated = await updateAdminStoreStatus(selected.id, !selected.is_active);
      setSelected(updated);
      setForm(mapAdminStoreFormValues(updated));
      await reloadList();
    } catch (err) {
      setError(err instanceof AdminStoresApiError ? err.message : "Unable to update status.");
    } finally {
      setSaving(false);
    }
  }

  async function handleBrandingUpload() {
    if (!canUpdate || !selected || !brandingUploadReady({ logo: logoFile, banner: bannerFile })) {
      return;
    }
    setSaving(true);
    setError(null);
    try {
      const updated = await uploadAdminStoreBranding(selected.id, {
        logo: logoFile,
        banner: bannerFile,
      });
      setSelected(updated);
      setLogoFile(null);
      setBannerFile(null);
    } catch (err) {
      setError(err instanceof AdminStoresApiError ? err.message : "Unable to upload branding.");
    } finally {
      setSaving(false);
    }
  }

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
        <AdminPageHeader title="Stores" />
        <div className="admin-card p-6">
          <p className="text-sm text-zinc-600">
            You need <code className="text-zinc-900">stores.view</code> to open Store Manager.
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <AdminPageHeader
        title={title}
        description="Manage TZ_LOCAL store identity, status, and branding. Settings stay on the settings page."
        actions={
          <div className="flex flex-wrap gap-2">
            {mode !== "list" ? (
              <button
                type="button"
                onClick={() => {
                  setMode("list");
                  setSelected(null);
                  void reloadList();
                }}
                className="admin-btn-secondary"
              >
                Back to list
              </button>
            ) : null}
            {mode === "list" && canCreate ? (
              <button
                type="button"
                onClick={() => {
                  setMode("create");
                  setForm(emptyForm());
                  setLogoFile(null);
                  setBannerFile(null);
                  setSelected(null);
                }}
                className="admin-btn-primary"
              >
                Create store
              </button>
            ) : null}
          </div>
        }
      />

      {error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      ) : null}

      {mode === "list" ? (
        loading ? (
          <div className="admin-card p-6 text-sm text-zinc-600">Loading stores…</div>
        ) : rows.length === 0 ? (
          <AdminEmptyState title="No stores yet" />
        ) : (
          <div className="admin-card overflow-hidden">
            <div className="admin-table-scroll">
              <table className="admin-table min-w-full">
                <thead>
                  <tr>
                    <th>Store</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => (
                    <tr key={row.id}>
                      <td>
                        <div className="flex items-center gap-3">
                          {row.logoUrl ? (
                            // eslint-disable-next-line @next/next/no-img-element
                            <img
                              src={row.logoUrl}
                              alt=""
                              className="h-10 w-10 rounded-lg object-cover"
                            />
                          ) : (
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-zinc-100 text-xs font-semibold text-zinc-700">
                              {row.name.slice(0, 1)}
                            </div>
                          )}
                          <div>
                            <p className="font-semibold text-zinc-900">{row.name}</p>
                            <p className="text-xs text-zinc-600">{row.code}</p>
                          </div>
                        </div>
                      </td>
                      <td>{row.slug}</td>
                      <td>
                        <span
                          className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
                            row.isActive
                              ? "bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200"
                              : "bg-amber-50 text-amber-800 ring-1 ring-amber-200"
                          }`}
                        >
                          {row.statusLabel}
                        </span>
                      </td>
                      <td className="text-zinc-600">
                        {row.createdAt ? new Date(row.createdAt).toLocaleDateString() : "—"}
                      </td>
                      <td>
                        <div className="flex gap-2">
                          <button
                            type="button"
                            onClick={() => void openEdit(row.id)}
                            className="font-medium text-[#8b6914] hover:underline"
                          >
                            View
                          </button>
                          {canUpdate ? (
                            <button
                              type="button"
                              onClick={() => void openEdit(row.id)}
                              className="font-medium text-zinc-700 hover:underline"
                            >
                              Edit
                            </button>
                          ) : null}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )
      ) : null}

      {mode === "create" ? (
        <form onSubmit={(e) => void handleCreateSubmit(e)} className="admin-card space-y-4 p-5">
          <StoreIdentityFields form={form} setForm={setForm} includeCode disabled={saving} />
          <div className="grid gap-4 md:grid-cols-2">
            <label className="admin-label">
              Logo
              <input
                type="file"
                accept="image/*"
                className="mt-1 block w-full text-sm text-zinc-700"
                onChange={(e) => setLogoFile(e.target.files?.[0] ?? null)}
              />
            </label>
            <label className="admin-label">
              Banner
              <input
                type="file"
                accept="image/*"
                className="mt-1 block w-full text-sm text-zinc-700"
                onChange={(e) => setBannerFile(e.target.files?.[0] ?? null)}
              />
            </label>
          </div>
          <button
            type="submit"
            disabled={saving || !canCreate}
            className="admin-btn-primary disabled:opacity-40"
          >
            {saving ? "Creating…" : "Create store"}
          </button>
        </form>
      ) : null}

      {mode === "edit" && selected ? (
        <div className="space-y-4">
          <div className="flex flex-wrap gap-2 border-b border-zinc-200 pb-3">
            {(["overview", "branding", "settings", "team", "activity"] as EditTab[]).map((key) => (
              <button
                key={key}
                type="button"
                onClick={() => setTab(key)}
                className={`rounded-lg px-3 py-1.5 text-sm capitalize font-medium ${
                  tab === key
                    ? "bg-[#c9a227]/15 text-[#8b6914] ring-1 ring-[#c9a227]/40"
                    : "text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900"
                }`}
              >
                {key}
              </button>
            ))}
          </div>

          {tab === "overview" ? (
            <form
              onSubmit={(e) => void handleUpdateSubmit(e)}
              className="admin-card space-y-4 p-5"
            >
                <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                  Store identity
                </p>
                <div className="flex flex-wrap gap-2">
                  <Link
                    href={`/admin/categories?origin=tz&store_id=${encodeURIComponent(selected.id)}`}
                    className="rounded-lg border border-zinc-300 px-3 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-50"
                  >
                    Manage store categories
                  </Link>
                  <Link
                    href={`/admin/stores/${selected.id}/dashboard`}
                    className="rounded-lg border border-[#c9a227]/40 px-3 py-1 text-xs font-medium text-[#8b6914] hover:bg-[#c9a227]/10"
                  >
                    Open dashboard →
                  </Link>
                </div>
              </div>
              <StoreIdentityFields
                form={form}
                setForm={setForm}
                includeCode={false}
                disabled={saving || !canUpdate}
              />
              <div className="flex flex-wrap gap-2">
                {canUpdate ? (
                  <>
                    <button
                      type="submit"
                      disabled={saving}
                      className="admin-btn-primary disabled:opacity-40"
                    >
                      {saving ? "Saving…" : "Save changes"}
                    </button>
                    <button
                      type="button"
                      disabled={saving}
                      onClick={() => void handleStatusToggle()}
                      className="admin-btn-secondary disabled:opacity-40"
                    >
                      {selected.is_active ? "Deactivate" : "Activate"}
                    </button>
                  </>
                ) : (
                  <p className="text-sm text-zinc-600">
                    You need <code className="text-zinc-900">stores.update</code> to edit this store.
                  </p>
                )}
              </div>
            </form>
          ) : null}

          {tab === "branding" ? (
            <div className="admin-card space-y-4 p-5">
              <div className="grid gap-4 md:grid-cols-2">
                <div>
                  <p className="text-xs font-semibold uppercase tracking-wide text-zinc-600">Logo</p>
                  {selected.logo_url ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                      src={selected.logo_url}
                      alt=""
                      className="mt-2 h-24 w-24 rounded-lg object-cover"
                    />
                  ) : (
                    <p className="mt-2 text-sm text-zinc-600">No logo uploaded.</p>
                  )}
                  <input
                    type="file"
                    accept="image/*"
                    disabled={!canUpdate || saving}
                    className="mt-3 block w-full text-sm text-zinc-700"
                    onChange={(e) => setLogoFile(e.target.files?.[0] ?? null)}
                  />
                </div>
                <div>
                  <p className="text-xs font-semibold uppercase tracking-wide text-zinc-600">
                    Banner
                  </p>
                  {selected.banner_url ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                      src={selected.banner_url}
                      alt=""
                      className="mt-2 h-24 w-full max-w-md rounded-lg object-cover"
                    />
                  ) : (
                    <p className="mt-2 text-sm text-zinc-600">No banner uploaded.</p>
                  )}
                  <input
                    type="file"
                    accept="image/*"
                    disabled={!canUpdate || saving}
                    className="mt-3 block w-full text-sm text-zinc-700"
                    onChange={(e) => setBannerFile(e.target.files?.[0] ?? null)}
                  />
                </div>
              </div>
              {canUpdate ? (
                <button
                  type="button"
                  disabled={saving || !brandingUploadReady({ logo: logoFile, banner: bannerFile })}
                  onClick={() => void handleBrandingUpload()}
                  className="admin-btn-primary disabled:opacity-40"
                >
                  {saving ? "Uploading…" : "Upload branding"}
                </button>
              ) : null}
            </div>
          ) : null}

          {tab === "settings" ? (
            <div className="admin-card p-5">
              <p className="text-sm text-zinc-700">
                Business, receipt, customer, and social settings are managed in Store Settings — not
                duplicated here.
              </p>
              <Link href="/admin/settings/store" className="admin-btn-secondary mt-3 inline-flex">
                Open store settings
              </Link>
            </div>
          ) : null}

          {tab === "team" ? <AdminStoreTeamPanel storeId={selected.id} /> : null}

          {tab === "activity" ? (
            <div className="admin-card p-5">
              {activity.length === 0 ? (
                <AdminEmptyState title="No recent activity for this store" />
              ) : (
                <ul className="divide-y divide-zinc-200">
                  {activity.map((row) => (
                    <li key={row.id} className="py-3">
                      <p className="text-sm text-zinc-900">
                        {row.description || row.event_type || "Event"}
                      </p>
                      <p className="mt-1 text-xs text-zinc-600">
                        {row.created_at ? new Date(row.created_at).toLocaleString() : "—"}
                        {row.actor?.name ? ` · ${row.actor.name}` : ""}
                      </p>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}

function StoreIdentityFields({
  form,
  setForm,
  includeCode,
  disabled,
}: {
  form: AdminStoreFormValues;
  setForm: React.Dispatch<React.SetStateAction<AdminStoreFormValues>>;
  includeCode: boolean;
  disabled?: boolean;
}) {
  return (
    <div className="grid gap-4 md:grid-cols-2">
      <label className="admin-label md:col-span-2">
        Name
        <input
          value={form.name}
          disabled={disabled}
          onChange={(e) => setForm((current) => ({ ...current, name: e.target.value }))}
          className="admin-input mt-1"
          required
        />
      </label>
      <label className="admin-label">
        Slug
        <input
          value={form.slug}
          disabled={disabled}
          onChange={(e) => setForm((current) => ({ ...current, slug: e.target.value }))}
          className="admin-input mt-1"
          placeholder="auto from name"
        />
      </label>
      {includeCode ? (
        <label className="admin-label">
          Code
          <input
            value={form.code}
            disabled={disabled}
            onChange={(e) => setForm((current) => ({ ...current, code: e.target.value }))}
            className="admin-input mt-1 uppercase"
            required
          />
        </label>
      ) : (
        <label className="admin-label">
          Theme color
          <input
            type="color"
            value={form.themeColor || "#1F4B3A"}
            disabled={disabled}
            onChange={(e) => setForm((current) => ({ ...current, themeColor: e.target.value }))}
            className="admin-input mt-1 h-10"
          />
        </label>
      )}
      {includeCode ? (
        <label className="admin-label">
          Theme color
          <input
            type="color"
            value={form.themeColor || "#1F4B3A"}
            disabled={disabled}
            onChange={(e) => setForm((current) => ({ ...current, themeColor: e.target.value }))}
            className="admin-input mt-1 h-10"
          />
        </label>
      ) : null}
      <label className="admin-label md:col-span-2">
        Description
        <textarea
          value={form.description}
          disabled={disabled}
          onChange={(e) => setForm((current) => ({ ...current, description: e.target.value }))}
          className="admin-input mt-1"
          rows={3}
        />
      </label>
      <label className="flex items-center gap-2 text-sm text-zinc-700">
        <input
          type="checkbox"
          checked={form.isActive}
          disabled={disabled}
          onChange={(e) => setForm((current) => ({ ...current, isActive: e.target.checked }))}
        />
        Active
      </label>
      <label className="flex items-center gap-2 text-sm text-zinc-700">
        <input
          type="checkbox"
          checked={form.storefrontVisible}
          disabled={disabled}
          onChange={(e) =>
            setForm((current) => ({ ...current, storefrontVisible: e.target.checked }))
          }
        />
        Storefront visible
      </label>
    </div>
  );
}
