"use client";

import { useCallback, useEffect, useState } from "react";
import { AdminEmptyState } from "@/components/admin/AdminEmptyState";
import { AdminPageHeader } from "@/components/admin/AdminPageHeader";
import {
  AdminGrowthApiError,
  createGrowthCampaign,
  createGrowthJourney,
  createGrowthSegment,
  fetchCampaignAnalytics,
  fetchGrowthCampaigns,
  fetchGrowthDashboard,
  fetchGrowthJourneys,
  fetchGrowthSegments,
  refreshGrowthSegment,
  runGrowthJourneys,
  sendGrowthCampaign,
  type CampaignAnalytics,
  type GrowthCampaign,
  type GrowthDashboard,
  type GrowthJourney,
  type GrowthSegment,
} from "@/lib/api/admin-growth";

type Tab = "segments" | "campaigns" | "journeys" | "analytics" | "templates" | "settings";

const CHANNEL_PRIORITY = ["WhatsApp", "Email", "In-app", "Push", "SMS"];

export function AdminGrowthPanel() {
  const [tab, setTab] = useState<Tab>("segments");
  const [dashboard, setDashboard] = useState<GrowthDashboard | null>(null);
  const [segments, setSegments] = useState<GrowthSegment[]>([]);
  const [campaigns, setCampaigns] = useState<GrowthCampaign[]>([]);
  const [journeys, setJourneys] = useState<GrowthJourney[]>([]);
  const [analytics, setAnalytics] = useState<CampaignAnalytics | null>(null);
  const [analyticsCampaignId, setAnalyticsCampaignId] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const [segmentForm, setSegmentForm] = useState({
    name: "",
    field: "total_spend",
    op: "gte",
    value: "500000",
  });
  const [campaignForm, setCampaignForm] = useState({
    name: "",
    campaign_type: "retention",
    growth_segment_id: "",
    channel: "in_app",
    message_body: "",
    bonus_points: "",
    create_promotion: false,
  });
  const [journeyForm, setJourneyForm] = useState({
    name: "",
    trigger_type: "registration",
    growth_segment_id: "",
    growth_campaign_id: "",
  });

  const reload = useCallback(async () => {
    setError(null);
    try {
      const [d, s, c, j] = await Promise.all([
        fetchGrowthDashboard(),
        fetchGrowthSegments(),
        fetchGrowthCampaigns(),
        fetchGrowthJourneys(),
      ]);
      setDashboard(d);
      setSegments(s);
      setCampaigns(c);
      setJourneys(j);
      setCampaignForm((prev) =>
        prev.growth_segment_id || !s[0] ? prev : { ...prev, growth_segment_id: s[0].id },
      );
    } catch (err) {
      setError(err instanceof AdminGrowthApiError ? err.message : "Unable to load growth platform.");
    }
  }, []);

  useEffect(() => {
    void reload();
  }, [reload]);

  const onCreateSegment = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!segmentForm.name.trim()) return;
    setBusy(true);
    setError(null);
    try {
      await createGrowthSegment({
        name: segmentForm.name.trim(),
        rules: {
          all: [
            {
              field: segmentForm.field,
              op: segmentForm.op,
              value: Number.isNaN(Number(segmentForm.value))
                ? segmentForm.value
                : Number(segmentForm.value),
            },
          ],
        },
      });
      setSegmentForm({ name: "", field: "total_spend", op: "gte", value: "500000" });
      await reload();
    } catch (err) {
      setError(err instanceof AdminGrowthApiError ? err.message : "Segment create failed.");
    } finally {
      setBusy(false);
    }
  };

  const onCreateCampaign = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!campaignForm.name.trim() || !campaignForm.message_body.trim()) return;
    setBusy(true);
    setError(null);
    try {
      await createGrowthCampaign({
        name: campaignForm.name.trim(),
        campaign_type: campaignForm.campaign_type,
        growth_segment_id: campaignForm.growth_segment_id || null,
        channel: campaignForm.channel,
        channels: [campaignForm.channel],
        message_body: campaignForm.message_body.trim(),
        bonus_points: campaignForm.bonus_points ? Number(campaignForm.bonus_points) : null,
        create_promotion: campaignForm.create_promotion,
        promotion: campaignForm.create_promotion
          ? { discount_type: "percentage", value: 10, days: 30 }
          : undefined,
      });
      setCampaignForm((prev) => ({
        ...prev,
        name: "",
        message_body: "",
        bonus_points: "",
        create_promotion: false,
      }));
      await reload();
    } catch (err) {
      setError(err instanceof AdminGrowthApiError ? err.message : "Campaign create failed.");
    } finally {
      setBusy(false);
    }
  };

  const onCreateJourney = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!journeyForm.name.trim()) return;
    setBusy(true);
    setError(null);
    try {
      await createGrowthJourney({
        name: journeyForm.name.trim(),
        trigger_type: journeyForm.trigger_type,
        growth_segment_id: journeyForm.growth_segment_id || null,
        growth_campaign_id: journeyForm.growth_campaign_id || null,
        trigger_config:
          journeyForm.trigger_type === "inactive_days"
            ? { days: 90 }
            : journeyForm.trigger_type === "vip_threshold"
              ? { min_spend: 500000 }
              : { within_days: 7 },
      });
      setJourneyForm({
        name: "",
        trigger_type: "registration",
        growth_segment_id: "",
        growth_campaign_id: "",
      });
      await reload();
    } catch (err) {
      setError(err instanceof AdminGrowthApiError ? err.message : "Journey create failed.");
    } finally {
      setBusy(false);
    }
  };

  const tabs: Array<{ id: Tab; label: string }> = [
    { id: "segments", label: "Customer Segments" },
    { id: "campaigns", label: "Campaigns" },
    { id: "journeys", label: "Customer Journeys" },
    { id: "analytics", label: "Campaign Analytics" },
    { id: "templates", label: "Templates" },
    { id: "settings", label: "Communication Settings" },
  ];

  return (
    <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <AdminPageHeader
        title="Growth Platform"
        description="Segments, campaigns, and journeys orchestrate CRM, Loyalty, Promotions, and Notifications — without duplicating those engines."
      />

      {error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      ) : null}

      {dashboard ? (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {[
            ["Active segments", dashboard.active_segments],
            ["Campaigns completed", dashboard.campaigns_completed],
            ["Campaign revenue", dashboard.campaign_revenue],
            ["Conversion %", dashboard.campaign_conversion_rate],
          ].map(([label, value]) => (
            <div key={String(label)} className="admin-stat-card px-4 py-4 sm:px-5">
              <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-600">
                {label}
              </p>
              <p className="mt-2 text-xl font-bold text-zinc-900">{value}</p>
            </div>
          ))}
        </div>
      ) : null}

      {dashboard ? (
        <div className="admin-card p-4 text-sm text-zinc-700">
          <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-600">
            Lifecycle
          </p>
          <div className="flex flex-wrap gap-4">
            {Object.entries(dashboard.lifecycle_distribution).map(([key, value]) => (
              <span key={key}>
                {key}: <span className="font-semibold text-zinc-900">{value}</span>
              </span>
            ))}
          </div>
        </div>
      ) : null}

      <div className="flex flex-wrap gap-2 border-b border-zinc-200 pb-2">
        {tabs.map((item) => (
          <button
            key={item.id}
            type="button"
            onClick={() => setTab(item.id)}
            className={`rounded-lg px-3 py-1.5 text-sm font-medium ${
              tab === item.id
                ? "bg-[#c9a227]/15 text-[#8b6914] ring-1 ring-[#c9a227]/40"
                : "border border-zinc-200 text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900"
            }`}
          >
            {item.label}
          </button>
        ))}
      </div>

      {tab === "segments" ? (
        <section className="space-y-4">
          <form onSubmit={onCreateSegment} className="admin-card grid gap-2 p-4 md:grid-cols-5">
            <input
              value={segmentForm.name}
              onChange={(e) => setSegmentForm((p) => ({ ...p, name: e.target.value }))}
              placeholder="Segment name"
              className="admin-input md:col-span-2"
            />
            <select
              value={segmentForm.field}
              onChange={(e) => setSegmentForm((p) => ({ ...p, field: e.target.value }))}
              className="admin-input"
            >
              <option value="total_spend">total_spend</option>
              <option value="total_orders">total_orders</option>
              <option value="days_since_last_order">days_since_last_order</option>
              <option value="loyalty_tier">loyalty_tier</option>
              <option value="tag">tag</option>
              <option value="growth_stage">growth_stage</option>
            </select>
            <select
              value={segmentForm.op}
              onChange={(e) => setSegmentForm((p) => ({ ...p, op: e.target.value }))}
              className="admin-input"
            >
              <option value="gte">gte</option>
              <option value="lte">lte</option>
              <option value="eq">eq</option>
              <option value="gt">gt</option>
              <option value="lt">lt</option>
            </select>
            <input
              value={segmentForm.value}
              onChange={(e) => setSegmentForm((p) => ({ ...p, value: e.target.value }))}
              placeholder="Value"
              className="admin-input"
            />
            <button
              type="submit"
              disabled={busy}
              className="admin-btn-primary md:col-span-5 md:w-fit disabled:opacity-50"
            >
              Create segment
            </button>
          </form>

          <div className="admin-card overflow-hidden">
            <div className="admin-table-scroll">
              <table className="admin-table min-w-full">
                <thead>
                  <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Members</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {segments.length === 0 ? (
                    <tr>
                      <td colSpan={4} className="!p-0">
                        <AdminEmptyState title="No segments yet" />
                      </td>
                    </tr>
                  ) : (
                    segments.map((segment) => (
                      <tr key={segment.id}>
                        <td className="font-mono text-xs">{segment.code}</td>
                        <td className="admin-table-primary">{segment.name}</td>
                        <td>{segment.member_count}</td>
                        <td>
                          <button
                            type="button"
                            disabled={busy}
                            onClick={() => {
                              setBusy(true);
                              void refreshGrowthSegment(segment.id)
                                .then(() => reload())
                                .catch((err) =>
                                  setError(
                                    err instanceof AdminGrowthApiError
                                      ? err.message
                                      : "Refresh failed.",
                                  ),
                                )
                                .finally(() => setBusy(false));
                            }}
                            className="admin-btn-secondary !px-2 !py-1 text-xs"
                          >
                            Refresh
                          </button>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </section>
      ) : null}

      {tab === "campaigns" ? (
        <section className="space-y-4">
          <form onSubmit={onCreateCampaign} className="admin-card grid gap-2 p-4 md:grid-cols-2">
            <input
              value={campaignForm.name}
              onChange={(e) => setCampaignForm((p) => ({ ...p, name: e.target.value }))}
              placeholder="Campaign name"
              className="admin-input"
            />
            <select
              value={campaignForm.campaign_type}
              onChange={(e) => setCampaignForm((p) => ({ ...p, campaign_type: e.target.value }))}
              className="admin-input"
            >
              <option value="promotion">Promotion</option>
              <option value="announcement">Announcement</option>
              <option value="new_product">New Product</option>
              <option value="retention">Retention</option>
              <option value="birthday">Birthday</option>
              <option value="winback">Win-back</option>
              <option value="vip">VIP</option>
            </select>
            <select
              value={campaignForm.growth_segment_id}
              onChange={(e) => setCampaignForm((p) => ({ ...p, growth_segment_id: e.target.value }))}
              className="admin-input"
            >
              <option value="">Target segment</option>
              {segments.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name} ({s.member_count})
                </option>
              ))}
            </select>
            <select
              value={campaignForm.channel}
              onChange={(e) => setCampaignForm((p) => ({ ...p, channel: e.target.value }))}
              className="admin-input"
            >
              <option value="whatsapp">WhatsApp</option>
              <option value="email">Email</option>
              <option value="in_app">In-app</option>
              <option value="push">Push</option>
              <option value="sms">SMS</option>
            </select>
            <textarea
              value={campaignForm.message_body}
              onChange={(e) => setCampaignForm((p) => ({ ...p, message_body: e.target.value }))}
              placeholder="Message body"
              rows={3}
              className="admin-input md:col-span-2"
            />
            <input
              value={campaignForm.bonus_points}
              onChange={(e) => setCampaignForm((p) => ({ ...p, bonus_points: e.target.value }))}
              placeholder="Bonus loyalty points (optional)"
              className="admin-input"
            />
            <label className="flex items-center gap-2 text-sm text-zinc-700">
              <input
                type="checkbox"
                checked={campaignForm.create_promotion}
                onChange={(e) =>
                  setCampaignForm((p) => ({ ...p, create_promotion: e.target.checked }))
                }
              />
              Attach promotion coupon
            </label>
            <button
              type="submit"
              disabled={busy}
              className="admin-btn-primary md:col-span-2 md:w-fit disabled:opacity-50"
            >
              Create campaign
            </button>
          </form>

          <div className="admin-card overflow-hidden">
            <div className="admin-table-scroll">
              <table className="admin-table min-w-full">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Sent</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {campaigns.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="!p-0">
                        <AdminEmptyState title="No campaigns yet" />
                      </td>
                    </tr>
                  ) : (
                    campaigns.map((campaign) => (
                      <tr key={campaign.id}>
                        <td className="admin-table-primary">{campaign.name}</td>
                        <td>{campaign.campaign_type}</td>
                        <td className="capitalize">{campaign.status}</td>
                        <td>{campaign.sent_count}</td>
                        <td>
                          <button
                            type="button"
                            disabled={busy || campaign.status === "completed"}
                            onClick={() => {
                              setBusy(true);
                              void sendGrowthCampaign(campaign.id)
                                .then(() => reload())
                                .catch((err) =>
                                  setError(
                                    err instanceof AdminGrowthApiError
                                      ? err.message
                                      : "Send failed.",
                                  ),
                                )
                                .finally(() => setBusy(false));
                            }}
                            className="admin-btn-secondary !px-2 !py-1 text-xs disabled:opacity-40"
                          >
                            Send
                          </button>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </section>
      ) : null}

      {tab === "journeys" ? (
        <section className="space-y-4">
          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              disabled={busy}
              onClick={() => {
                setBusy(true);
                void runGrowthJourneys(false)
                  .then(() => reload())
                  .catch((err) =>
                    setError(err instanceof AdminGrowthApiError ? err.message : "Run failed."),
                  )
                  .finally(() => setBusy(false));
              }}
              className="admin-btn-secondary disabled:opacity-50"
            >
              Run journey triggers
            </button>
          </div>
          <form onSubmit={onCreateJourney} className="admin-card grid gap-2 p-4 md:grid-cols-2">
            <input
              value={journeyForm.name}
              onChange={(e) => setJourneyForm((p) => ({ ...p, name: e.target.value }))}
              placeholder="Journey name"
              className="admin-input"
            />
            <select
              value={journeyForm.trigger_type}
              onChange={(e) => setJourneyForm((p) => ({ ...p, trigger_type: e.target.value }))}
              className="admin-input"
            >
              <option value="registration">Registration</option>
              <option value="inactive_days">Inactive days</option>
              <option value="vip_threshold">VIP threshold</option>
              <option value="birthday">Birthday</option>
              <option value="manual">Manual</option>
            </select>
            <select
              value={journeyForm.growth_segment_id}
              onChange={(e) => setJourneyForm((p) => ({ ...p, growth_segment_id: e.target.value }))}
              className="admin-input"
            >
              <option value="">Optional segment</option>
              {segments.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </select>
            <select
              value={journeyForm.growth_campaign_id}
              onChange={(e) => setJourneyForm((p) => ({ ...p, growth_campaign_id: e.target.value }))}
              className="admin-input"
            >
              <option value="">Optional campaign</option>
              {campaigns.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
            <button
              type="submit"
              disabled={busy}
              className="admin-btn-primary md:col-span-2 md:w-fit disabled:opacity-50"
            >
              Create journey
            </button>
          </form>
          {journeys.length === 0 ? (
            <AdminEmptyState title="No journeys yet" />
          ) : (
            <ul className="space-y-2">
              {journeys.map((journey) => (
                <li key={journey.id} className="admin-card px-3 py-2 text-sm text-zinc-700">
                  <span className="font-medium text-zinc-900">{journey.name}</span>
                  <span className="ml-2 text-zinc-600">{journey.trigger_type}</span>
                  {journey.segment ? (
                    <span className="ml-2 text-zinc-600">→ {journey.segment.name}</span>
                  ) : null}
                </li>
              ))}
            </ul>
          )}
        </section>
      ) : null}

      {tab === "analytics" ? (
        <section className="space-y-4">
          <div className="flex flex-wrap items-end gap-2">
            <select
              value={analyticsCampaignId}
              onChange={(e) => setAnalyticsCampaignId(e.target.value)}
              className="admin-input max-w-xs"
            >
              <option value="">Select campaign</option>
              {campaigns.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
            <button
              type="button"
              disabled={!analyticsCampaignId || busy}
              onClick={() => {
                setBusy(true);
                void fetchCampaignAnalytics(analyticsCampaignId)
                  .then(setAnalytics)
                  .catch((err) =>
                    setError(err instanceof AdminGrowthApiError ? err.message : "Analytics failed."),
                  )
                  .finally(() => setBusy(false));
              }}
              className="admin-btn-secondary disabled:opacity-50"
            >
              Load analytics
            </button>
          </div>
          {analytics ? (
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              {(
                [
                  ["Sent", analytics.sent],
                  ["Opened", analytics.opened],
                  ["Clicked", analytics.clicked],
                  ["Purchased", analytics.purchased],
                  ["Conversion %", analytics.conversion_rate],
                  ["Revenue", analytics.revenue_generated],
                ] as const
              ).map(([label, value]) => (
                <div key={label} className="admin-stat-card px-4 py-4 sm:px-5">
                  <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-600">
                    {label}
                  </p>
                  <p className="mt-2 text-xl font-bold text-zinc-900">{value}</p>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-sm text-zinc-600">
              Select a campaign to view engagement. Platform KPIs also appear under Analytics →
              Growth.
            </p>
          )}
        </section>
      ) : null}

      {tab === "templates" ? (
        <section className="admin-card p-4 text-sm text-zinc-700">
          <p className="font-semibold text-zinc-900">Message templates</p>
          <p className="mt-2 text-zinc-600">
            Growth campaigns reuse the Notification Platform templates keyed by{" "}
            <code className="text-zinc-800">growth_campaign.&#123;channel&#125;</code>. Manage copy
            in Notification Templates — this platform does not store a parallel template system.
          </p>
          <a
            href="/admin/notification-templates"
            className="mt-3 inline-block font-medium text-[#8b6914] underline"
          >
            Open notification templates
          </a>
        </section>
      ) : null}

      {tab === "settings" ? (
        <section className="admin-card p-4 text-sm text-zinc-700">
          <p className="font-semibold text-zinc-900">Channel priority</p>
          <ol className="mt-3 list-decimal space-y-1 pl-5 text-zinc-600">
            {CHANNEL_PRIORITY.map((channel) => (
              <li key={channel}>{channel}</li>
            ))}
          </ol>
          <p className="mt-4 text-zinc-600">
            Delivery credentials and providers live in Notification Settings. Growth only selects
            channels and event type <code className="text-zinc-800">growth_campaign</code>.
          </p>
          <a
            href="/admin/notifications"
            className="mt-3 inline-block font-medium text-[#8b6914] underline"
          >
            Open notification settings
          </a>
        </section>
      ) : null}
    </div>
  );
}
