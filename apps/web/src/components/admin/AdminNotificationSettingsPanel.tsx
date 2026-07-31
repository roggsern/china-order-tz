"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";
import {
  AdminNotificationConfigApiError,
  CHANNEL_TOGGLE_LABELS,
  EVENT_LABELS,
  canManageNotificationConfig,
  canViewNotificationConfig,
  fetchAdminNotificationConfig,
  updateAdminNotificationConfig,
  type NotificationChannelToggles,
} from "@/lib/api/admin-notification-config";

const CHANNEL_ORDER: (keyof NotificationChannelToggles)[] = [
  "in_app_enabled",
  "email_enabled",
  "sms_enabled",
  "whatsapp_enabled",
  "push_enabled",
];

const CHANNEL_VALUES = ["in_app", "email", "sms", "whatsapp", "push"] as const;

function defaultToggles(): NotificationChannelToggles {
  return {
    email_enabled: false,
    sms_enabled: false,
    whatsapp_enabled: false,
    push_enabled: false,
    in_app_enabled: true,
  };
}

export function AdminNotificationSettingsPanel() {
  const { permissions, loading: permissionsLoading } = useAdminPermissions();
  const canView = canViewNotificationConfig(permissions);
  const canManage = canManageNotificationConfig(permissions);

  const [channels, setChannels] = useState<NotificationChannelToggles>(defaultToggles);
  const [eventMap, setEventMap] = useState<Record<string, string[]>>({});
  const [providerStatus, setProviderStatus] = useState<
    Record<string, { configured: boolean; driver?: string }>
  >({});
  const [allowedChannels, setAllowedChannels] = useState<string[]>([...CHANNEL_VALUES]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const managedEvents = useMemo(
    () => Object.keys(eventMap).sort((a, b) => a.localeCompare(b)),
    [eventMap],
  );

  const reload = useCallback(async () => {
    if (!canView) {
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);
    try {
      const config = await fetchAdminNotificationConfig();
      setChannels({ ...defaultToggles(), ...config.channels });
      setEventMap(config.event_channel_map ?? {});
      setProviderStatus(config.provider_status ?? {});
      if (config.allowed_channels?.length) {
        setAllowedChannels(config.allowed_channels);
      }
    } catch (err) {
      setError(
        err instanceof AdminNotificationConfigApiError
          ? err.message
          : "Unable to load notification configuration.",
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

  const toggleChannel = (key: keyof NotificationChannelToggles, value: boolean) => {
    setChannels((prev) => ({ ...prev, [key]: value }));
    setSuccess(null);
  };

  const toggleEventChannel = (eventKey: string, channel: string, enabled: boolean) => {
    setEventMap((prev) => {
      const current = new Set(prev[eventKey] ?? []);
      if (enabled) {
        current.add(channel);
      } else {
        current.delete(channel);
      }
      if (current.size === 0) {
        current.add("in_app");
      }
      return { ...prev, [eventKey]: Array.from(current) };
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
      const updated = await updateAdminNotificationConfig({
        channels,
        event_channel_map: eventMap,
      });
      setChannels({ ...defaultToggles(), ...updated.channels });
      setEventMap(updated.event_channel_map ?? {});
      setProviderStatus(updated.provider_status ?? {});
      setSuccess("Notification configuration saved.");
    } catch (err) {
      setError(
        err instanceof AdminNotificationConfigApiError
          ? err.message
          : "Unable to update notification configuration.",
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
        <h1 className="text-xl font-semibold text-zinc-100">Notification configuration</h1>
        <p className="mt-2 text-sm text-zinc-400">
          You need <code className="text-zinc-300">notifications.view</code> to open this page.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-zinc-100">Notification configuration</h1>
        <p className="mt-1 text-sm text-zinc-400">
          Configure delivery channels and event mappings. SMTP/API credentials stay in environment
          variables and are never stored here.
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
          Loading notification configuration…
        </div>
      ) : (
        <>
          <section className="rounded-xl border border-zinc-800 bg-zinc-950/50 p-5">
            <h2 className="text-lg font-medium text-zinc-100">Channels</h2>
            <p className="mt-1 text-sm text-zinc-500">
              Disabled channels are skipped. Unconfigured external providers fall back to in-app.
            </p>
            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {CHANNEL_ORDER.map((key) => {
                const channelName = key.replace(/_enabled$/, "");
                const status = providerStatus[channelName];
                return (
                  <label
                    key={key}
                    className="flex items-start gap-3 rounded-lg border border-zinc-800 bg-zinc-900/50 px-3 py-3"
                  >
                    <input
                      type="checkbox"
                      checked={Boolean(channels[key])}
                      disabled={!canManage || saving}
                      onChange={(event) => toggleChannel(key, event.target.checked)}
                      className="mt-1 h-4 w-4 rounded border-zinc-600 bg-zinc-900"
                    />
                    <span>
                      <span className="block text-sm font-medium text-zinc-100">
                        {CHANNEL_TOGGLE_LABELS[key]}
                      </span>
                      <span className="mt-0.5 block text-xs text-zinc-500">
                        {status?.configured
                          ? `Provider ready (${status.driver ?? channelName})`
                          : channelName === "in_app"
                            ? "Always available"
                            : "Provider not configured in ENV"}
                      </span>
                    </span>
                  </label>
                );
              })}
            </div>
          </section>

          <section className="rounded-xl border border-zinc-800 bg-zinc-950/50 p-5">
            <h2 className="text-lg font-medium text-zinc-100">Event channel mapping</h2>
            <p className="mt-1 text-sm text-zinc-500">
              Choose which channels fire for key commerce events.
            </p>
            <div className="mt-4 space-y-4">
              {managedEvents.map((eventKey) => (
                <div
                  key={eventKey}
                  className="rounded-lg border border-zinc-800 bg-zinc-900/40 px-4 py-3"
                >
                  <div className="mb-2">
                    <p className="text-sm font-medium text-zinc-100">
                      {EVENT_LABELS[eventKey] ?? eventKey}
                    </p>
                    <p className="text-xs text-zinc-500">{eventKey}</p>
                  </div>
                  <div className="flex flex-wrap gap-3">
                    {allowedChannels.map((channel) => {
                      const checked = (eventMap[eventKey] ?? []).includes(channel);
                      return (
                        <label
                          key={`${eventKey}-${channel}`}
                          className="inline-flex items-center gap-2 text-sm text-zinc-300"
                        >
                          <input
                            type="checkbox"
                            checked={checked}
                            disabled={!canManage || saving}
                            onChange={(event) =>
                              toggleEventChannel(eventKey, channel, event.target.checked)
                            }
                            className="h-4 w-4 rounded border-zinc-600 bg-zinc-900"
                          />
                          {channel}
                        </label>
                      );
                    })}
                  </div>
                </div>
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
