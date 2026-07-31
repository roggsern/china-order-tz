"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  buildPermissionEditorGroups,
  collectRolePermissionSlugs,
  computePermissionDraft,
  hasPermissionDraftChanges,
  permissionEditorSummary,
  previewHasHighRiskAdded,
  resolvePermissionSaveConfirmation,
  togglePermissionSlug,
  type RolePermissionPreview,
} from "@/lib/admin/admin-role-permission-editor";
import {
  formatPermissionDomainLabel,
  listPermissionDomains,
  permissionRiskBadgeClass,
  permissionRiskLabel,
  type AdminPermissionCatalogEntry,
  type PermissionRiskTier,
} from "@/lib/admin/admin-permission-catalog";
import {
  AdminPermissionsApiError,
  fetchAdminPermissionCatalog,
} from "@/lib/api/admin-permissions";
import {
  AdminRolesApiError,
  previewRolePermissionChanges,
  updateRolePermissions,
  type AdminRoleDetail,
} from "@/lib/api/admin-roles";

function RiskBadge({ tier }: { tier: PermissionRiskTier }) {
  return (
    <span
      className={`inline-flex rounded-full border px-2 py-0.5 text-[10px] font-semibold tracking-wide ${permissionRiskBadgeClass(tier)}`}
    >
      {permissionRiskLabel(tier)}
    </span>
  );
}

function PreviewPermissionList({
  title,
  permissions,
}: {
  title: string;
  permissions: RolePermissionPreview["added_permissions"];
}) {
  if (permissions.length === 0) {
    return null;
  }

  return (
    <div className="space-y-2">
      <h4 className="text-xs font-semibold uppercase tracking-wide text-zinc-500">{title}</h4>
      <ul className="space-y-1.5">
        {permissions.map((permission) => (
          <li
            key={permission.slug}
            className="flex items-center justify-between gap-2 rounded-md border border-zinc-800 bg-zinc-950/40 px-3 py-2"
          >
            <span className="font-mono text-xs text-zinc-300">{permission.slug}</span>
            <RiskBadge tier={permission.risk_tier} />
          </li>
        ))}
      </ul>
    </div>
  );
}

type AdminRolePermissionEditorProps = {
  roleId: string;
  detail: AdminRoleDetail;
  open: boolean;
  onClose: () => void;
  onSaved: (detail: AdminRoleDetail) => void;
};

export function AdminRolePermissionEditor({
  roleId,
  detail,
  open,
  onClose,
  onSaved,
}: AdminRolePermissionEditorProps) {
  const baselineSlugs = useMemo(() => collectRolePermissionSlugs(detail), [detail]);
  const [catalog, setCatalog] = useState<AdminPermissionCatalogEntry[]>([]);
  const [selectedSlugs, setSelectedSlugs] = useState<string[]>(baselineSlugs);
  const [search, setSearch] = useState("");
  const [domain, setDomain] = useState("");
  const [risk, setRisk] = useState<PermissionRiskTier | "">("");
  const [loadingCatalog, setLoadingCatalog] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [preview, setPreview] = useState<RolePermissionPreview | null>(null);

  const draft = useMemo(
    () => computePermissionDraft(baselineSlugs, selectedSlugs),
    [baselineSlugs, selectedSlugs],
  );

  const groupedRows = useMemo(
    () => buildPermissionEditorGroups(catalog, { search, domain, risk }),
    [catalog, search, domain, risk],
  );

  const domainOptions = useMemo(() => listPermissionDomains(catalog), [catalog]);
  const confirmation = preview ? resolvePermissionSaveConfirmation(preview) : null;

  const resetEditor = useCallback(() => {
    setSelectedSlugs(baselineSlugs);
    setSearch("");
    setDomain("");
    setRisk("");
    setError(null);
    setPreview(null);
  }, [baselineSlugs]);

  useEffect(() => {
    if (!open) {
      return;
    }

    resetEditor();
  }, [open, resetEditor]);

  useEffect(() => {
    if (!open || catalog.length > 0) {
      return;
    }

    let cancelled = false;

    void (async () => {
      setLoadingCatalog(true);
      setError(null);
      try {
        const response = await fetchAdminPermissionCatalog();
        if (!cancelled) {
          setCatalog(response.permissions);
        }
      } catch (err) {
        if (!cancelled) {
          setCatalog([]);
          setError(
            err instanceof AdminPermissionsApiError
              ? err.message
              : "Unable to load permission catalog.",
          );
        }
      } finally {
        if (!cancelled) {
          setLoadingCatalog(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [catalog.length, open]);

  const handleToggle = (slug: string) => {
    setSelectedSlugs((current) => togglePermissionSlug(current, slug));
    setPreview(null);
    setError(null);
  };

  const handleReview = async () => {
    if (!hasPermissionDraftChanges(draft)) {
      setError("Select at least one permission change before reviewing.");
      return;
    }

    setBusy(true);
    setError(null);
    try {
      setPreview(await previewRolePermissionChanges(roleId, draft));
    } catch (err) {
      setPreview(null);
      setError(
        err instanceof AdminRolesApiError ? err.message : "Unable to preview permission changes.",
      );
    } finally {
      setBusy(false);
    }
  };

  const handleConfirmSave = async () => {
    if (!preview || !hasPermissionDraftChanges(draft)) {
      return;
    }

    setBusy(true);
    setError(null);
    try {
      const updated = await updateRolePermissions(roleId, draft);
      onSaved(updated);
      onClose();
    } catch (err) {
      setError(
        err instanceof AdminRolesApiError ? err.message : "Unable to save permission changes.",
      );
    } finally {
      setBusy(false);
    }
  };

  if (!open) {
    return null;
  }

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/70 p-4 md:p-8">
      <div
        className="my-auto w-full max-w-5xl rounded-xl border border-zinc-800 bg-zinc-950 shadow-2xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="role-permission-editor-title"
      >
        <div className="flex items-start justify-between gap-4 border-b border-zinc-800 px-4 py-4 md:px-6">
          <div>
            <h2 id="role-permission-editor-title" className="text-lg font-semibold text-zinc-50">
              Edit permissions
            </h2>
            <p className="mt-1 text-sm text-zinc-400">
              {detail.role.name}
              <span className="ml-2 font-mono text-xs text-zinc-500">{detail.role.slug}</span>
            </p>
            <p className="mt-2 text-xs text-zinc-500">{permissionEditorSummary(draft)}</p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-md border border-zinc-700 px-3 py-1.5 text-sm text-zinc-300 hover:bg-zinc-900"
          >
            Close
          </button>
        </div>

        <div className="space-y-4 px-4 py-4 md:px-6">
          {error ? (
            <div className="rounded-md border border-red-900/50 bg-red-950/30 px-3 py-2 text-sm text-red-200">
              {error}
            </div>
          ) : null}

          <div className="grid gap-3 md:grid-cols-3">
            <label className="block text-sm">
              <span className="mb-1 block text-xs font-medium uppercase tracking-wide text-zinc-500">
                Search
              </span>
              <input
                type="search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Slug, name, domain…"
                className="w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100"
              />
            </label>
            <label className="block text-sm">
              <span className="mb-1 block text-xs font-medium uppercase tracking-wide text-zinc-500">
                Domain
              </span>
              <select
                value={domain}
                onChange={(event) => setDomain(event.target.value)}
                className="w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100"
              >
                <option value="">All domains</option>
                {domainOptions.map((option) => (
                  <option key={option} value={option}>
                    {formatPermissionDomainLabel(option)}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm">
              <span className="mb-1 block text-xs font-medium uppercase tracking-wide text-zinc-500">
                Risk
              </span>
              <select
                value={risk}
                onChange={(event) => setRisk(event.target.value as PermissionRiskTier | "")}
                className="w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100"
              >
                <option value="">All risk levels</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
              </select>
            </label>
          </div>

          {loadingCatalog ? (
            <p className="text-sm text-zinc-500">Loading permission catalog…</p>
          ) : groupedRows.length === 0 ? (
            <p className="text-sm text-zinc-500">No permissions match these filters.</p>
          ) : (
            <div className="max-h-[50vh] space-y-4 overflow-y-auto pr-1">
              {groupedRows.map((group) => (
                <section
                  key={group.domain}
                  className="rounded-lg border border-zinc-800 bg-zinc-900/30 p-4"
                >
                  <h3 className="text-sm font-semibold text-[#e8c547]">
                    {formatPermissionDomainLabel(group.domain)}
                  </h3>
                  <ul className="mt-3 space-y-2">
                    {group.permissions.map((permission) => {
                      const checked = selectedSlugs.includes(permission.slug);
                      const isBaseline = baselineSlugs.includes(permission.slug);
                      const pendingAdd = !isBaseline && checked;
                      const pendingRemove = isBaseline && !checked;

                      return (
                        <li
                          key={permission.id}
                          className="flex items-start gap-3 rounded-md border border-zinc-800/80 bg-zinc-950/30 px-3 py-2"
                        >
                          <input
                            type="checkbox"
                            checked={checked}
                            onChange={() => handleToggle(permission.slug)}
                            className="mt-1 h-4 w-4 rounded border-zinc-600 bg-zinc-900"
                          />
                          <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                              <span className="font-mono text-xs text-zinc-300">
                                {permission.slug}
                              </span>
                              <RiskBadge tier={permission.risk_tier} />
                              {pendingAdd ? (
                                <span className="text-[10px] font-semibold uppercase tracking-wide text-emerald-400">
                                  Add
                                </span>
                              ) : null}
                              {pendingRemove ? (
                                <span className="text-[10px] font-semibold uppercase tracking-wide text-amber-400">
                                  Remove
                                </span>
                              ) : null}
                            </div>
                            <p className="mt-1 text-sm text-zinc-400">{permission.name}</p>
                          </div>
                        </li>
                      );
                    })}
                  </ul>
                </section>
              ))}
            </div>
          )}
        </div>

        <div className="flex flex-wrap items-center justify-end gap-2 border-t border-zinc-800 px-4 py-4 md:px-6">
          <button
            type="button"
            onClick={resetEditor}
            disabled={busy}
            className="rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-300 hover:bg-zinc-900 disabled:opacity-50"
          >
            Reset draft
          </button>
          <button
            type="button"
            onClick={() => void handleReview()}
            disabled={busy || !hasPermissionDraftChanges(draft)}
            className="rounded-md bg-[#e8c547] px-4 py-2 text-sm font-semibold text-zinc-950 hover:bg-[#f0d058] disabled:opacity-50"
          >
            {busy && !preview ? "Reviewing…" : "Review changes"}
          </button>
        </div>

        {preview && confirmation ? (
          <div className="border-t border-zinc-800 bg-zinc-900/40 px-4 py-4 md:px-6">
            <h3 className="text-base font-semibold text-zinc-100">{confirmation.title}</h3>
            <p className="mt-2 text-sm text-zinc-400">{confirmation.message}</p>

            {confirmation.showHighRiskWarning ? (
              <div className="mt-3 rounded-md border border-red-900/60 bg-red-950/30 px-3 py-2 text-sm text-red-200">
                {confirmation.highRiskMessage}
              </div>
            ) : null}

            <div className="mt-4 grid gap-4 md:grid-cols-2">
              <PreviewPermissionList title="Added permissions" permissions={preview.added_permissions} />
              <PreviewPermissionList
                title="Removed permissions"
                permissions={preview.removed_permissions}
              />
            </div>

            {preview.warnings.length > 0 ? (
              <div className="mt-4 space-y-2">
                <h4 className="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                  Warnings
                </h4>
                {preview.warnings.map((warning) => (
                  <div
                    key={warning.code}
                    className="rounded-md border border-amber-900/50 bg-amber-950/20 px-3 py-2 text-sm text-amber-100"
                  >
                    <p className="font-medium">{warning.label}</p>
                    <p className="mt-1 text-amber-200/90">{warning.message}</p>
                  </div>
                ))}
              </div>
            ) : null}

            <div className="mt-4 flex flex-wrap justify-end gap-2">
              <button
                type="button"
                onClick={() => setPreview(null)}
                disabled={busy}
                className="rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-300 hover:bg-zinc-900 disabled:opacity-50"
              >
                Back to editor
              </button>
              <button
                type="button"
                onClick={() => void handleConfirmSave()}
                disabled={busy}
                className={`rounded-md px-4 py-2 text-sm font-semibold disabled:opacity-50 ${
                  previewHasHighRiskAdded(preview)
                    ? "bg-red-700 text-white hover:bg-red-600"
                    : "bg-emerald-700 text-white hover:bg-emerald-600"
                }`}
              >
                {busy ? "Saving…" : "Confirm and save"}
              </button>
            </div>
          </div>
        ) : null}
      </div>
    </div>
  );
}
