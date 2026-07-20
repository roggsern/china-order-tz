"use client";

import { useCallback, useEffect, useState } from "react";
import {
  AdminNotificationsApiError,
  fetchAdminNotificationTemplates,
  previewAdminNotificationTemplate,
  updateAdminNotificationTemplate,
  type AdminNotificationTemplate,
} from "@/lib/api/admin-notifications";

const SAMPLE_VARS: Record<string, string> = {
  customer_name: "Asha Mwangi",
  order_number: "COTZ-20260718-000001",
  order_total: "250000",
  currency: "TZS",
  shipment_number: "SHP-1001",
  tracking_status: "Out for delivery",
  otp_code: "482913",
  otp_expires_minutes: "10",
  reset_code: "RESET-DEMO",
};

export function AdminNotificationTemplatesPanel() {
  const [rows, setRows] = useState<AdminNotificationTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [draftBody, setDraftBody] = useState("");
  const [draftSubject, setDraftSubject] = useState("");
  const [preview, setPreview] = useState<{ subject: string | null; body: string } | null>(
    null,
  );
  const [busy, setBusy] = useState(false);

  const selected = rows.find((row) => row.id === selectedId) ?? null;

  const reload = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await fetchAdminNotificationTemplates();
      setRows(data);
      if (data.length > 0 && !selectedId) {
        setSelectedId(data[0].id);
        setDraftBody(data[0].body);
        setDraftSubject(data[0].subject ?? "");
      }
    } catch (err) {
      setRows([]);
      setError(
        err instanceof AdminNotificationsApiError
          ? err.message
          : "Unable to load templates.",
      );
    } finally {
      setLoading(false);
    }
  }, [selectedId]);

  useEffect(() => {
    void reload();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (!selected) return;
    setDraftBody(selected.body);
    setDraftSubject(selected.subject ?? "");
    setPreview(null);
  }, [selected]);

  const onSave = async () => {
    if (!selected) return;
    setBusy(true);
    setError(null);
    try {
      const updated = await updateAdminNotificationTemplate(selected.id, {
        body: draftBody,
        subject: draftSubject || null,
      });
      setRows((prev) => prev.map((row) => (row.id === updated.id ? updated : row)));
    } catch (err) {
      setError(
        err instanceof AdminNotificationsApiError ? err.message : "Unable to save.",
      );
    } finally {
      setBusy(false);
    }
  };

  const onToggle = async () => {
    if (!selected) return;
    setBusy(true);
    setError(null);
    try {
      const updated = await updateAdminNotificationTemplate(selected.id, {
        is_active: !selected.is_active,
      });
      setRows((prev) => prev.map((row) => (row.id === updated.id ? updated : row)));
    } catch (err) {
      setError(
        err instanceof AdminNotificationsApiError ? err.message : "Unable to update.",
      );
    } finally {
      setBusy(false);
    }
  };

  const onPreview = async () => {
    if (!selected) return;
    setBusy(true);
    setError(null);
    try {
      // Persist draft first so preview matches editor (optional: preview unsaved via local render).
      await updateAdminNotificationTemplate(selected.id, {
        body: draftBody,
        subject: draftSubject || null,
      });
      const rendered = await previewAdminNotificationTemplate(selected.id, SAMPLE_VARS);
      setPreview({ subject: rendered.subject, body: rendered.body });
      await reload();
      setSelectedId(selected.id);
    } catch (err) {
      setError(
        err instanceof AdminNotificationsApiError ? err.message : "Unable to preview.",
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-6 p-6">
      <div>
        <h1 className="text-2xl font-semibold text-zinc-900">Notification Templates</h1>
        <p className="mt-1 text-sm text-zinc-600">
          Enable, edit, and preview templates. Variables use {"{{name}}"} syntax.
        </p>
      </div>

      {error ? (
        <p className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </p>
      ) : null}

      <div className="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
        <div className="rounded-md border border-zinc-200 bg-white">
          <div className="border-b border-zinc-200 px-3 py-2 text-xs font-medium uppercase tracking-wide text-zinc-500">
            Templates
          </div>
          {loading ? (
            <p className="px-3 py-6 text-sm text-zinc-500">Loading…</p>
          ) : (
            <ul className="max-h-[70vh] overflow-y-auto divide-y divide-zinc-100">
              {rows.map((row) => (
                <li key={row.id}>
                  <button
                    type="button"
                    onClick={() => setSelectedId(row.id)}
                    className={`w-full px-3 py-3 text-left text-sm hover:bg-zinc-50 ${
                      selectedId === row.id ? "bg-amber-50" : ""
                    }`}
                  >
                    <p className="font-medium text-zinc-900">{row.name}</p>
                    <p className="mt-0.5 text-xs text-zinc-500">
                      {row.channel} · {row.is_active ? "Active" : "Disabled"}
                    </p>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>

        <div className="space-y-4 rounded-md border border-zinc-200 bg-white p-4">
          {!selected ? (
            <p className="text-sm text-zinc-500">Select a template to edit.</p>
          ) : (
            <>
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <h2 className="text-lg font-semibold text-zinc-900">{selected.name}</h2>
                  <p className="text-xs text-zinc-500">{selected.key}</p>
                </div>
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => void onToggle()}
                  className="rounded-md border border-zinc-300 px-3 py-1.5 text-sm font-medium disabled:opacity-50"
                >
                  {selected.is_active ? "Disable" : "Enable"}
                </button>
              </div>

              <label className="block text-sm">
                <span className="mb-1 block font-medium text-zinc-700">Subject</span>
                <input
                  value={draftSubject}
                  onChange={(e) => setDraftSubject(e.target.value)}
                  className="w-full rounded-md border border-zinc-300 px-3 py-2"
                />
              </label>

              <label className="block text-sm">
                <span className="mb-1 block font-medium text-zinc-700">Body</span>
                <textarea
                  value={draftBody}
                  onChange={(e) => setDraftBody(e.target.value)}
                  rows={8}
                  className="w-full rounded-md border border-zinc-300 px-3 py-2 font-mono text-sm"
                />
              </label>

              <div className="flex flex-wrap gap-2">
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => void onSave()}
                  className="rounded-md bg-zinc-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                  Save
                </button>
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => void onPreview()}
                  className="rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium disabled:opacity-50"
                >
                  Preview
                </button>
              </div>

              {preview ? (
                <div className="rounded-md border border-amber-200 bg-amber-50/60 p-4">
                  <p className="text-xs font-medium uppercase tracking-wide text-amber-800">
                    Preview
                  </p>
                  {preview.subject ? (
                    <p className="mt-2 text-sm font-semibold text-zinc-900">{preview.subject}</p>
                  ) : null}
                  <p className="mt-2 whitespace-pre-wrap text-sm text-zinc-700">{preview.body}</p>
                </div>
              ) : null}
            </>
          )}
        </div>
      </div>
    </div>
  );
}
