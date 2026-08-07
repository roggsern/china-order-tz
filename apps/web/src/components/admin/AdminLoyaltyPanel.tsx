"use client";

import { useCallback, useEffect, useState } from "react";
import { AdminEmptyState } from "@/components/admin/AdminEmptyState";
import { AdminPageHeader } from "@/components/admin/AdminPageHeader";
import {
  AdminLoyaltyApiError,
  adjustLoyaltyPoints,
  createLoyaltyReward,
  createLoyaltyRule,
  createLoyaltyTier,
  fetchLoyaltyCustomers,
  fetchLoyaltyDashboard,
  fetchLoyaltyRewards,
  fetchLoyaltyRules,
  fetchLoyaltyTiers,
  updateLoyaltyReward,
  updateLoyaltyRule,
  type LoyaltyAccount,
  type LoyaltyDashboard,
  type LoyaltyReward,
  type LoyaltyRule,
  type LoyaltyTier,
} from "@/lib/api/admin-loyalty";

export function AdminLoyaltyPanel() {
  const [dashboard, setDashboard] = useState<LoyaltyDashboard | null>(null);
  const [customers, setCustomers] = useState<LoyaltyAccount[]>([]);
  const [tiers, setTiers] = useState<LoyaltyTier[]>([]);
  const [rules, setRules] = useState<LoyaltyRule[]>([]);
  const [rewards, setRewards] = useState<LoyaltyReward[]>([]);
  const [search, setSearch] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [adjust, setAdjust] = useState({ accountId: "", points: "50", reason: "" });

  const reload = useCallback(async () => {
    setError(null);
    try {
      const [d, c, t, r, rw] = await Promise.all([
        fetchLoyaltyDashboard(),
        fetchLoyaltyCustomers(search),
        fetchLoyaltyTiers(),
        fetchLoyaltyRules(),
        fetchLoyaltyRewards(),
      ]);
      setDashboard(d);
      setCustomers(c);
      setTiers(t);
      setRules(r);
      setRewards(rw);
    } catch (err) {
      setError(err instanceof AdminLoyaltyApiError ? err.message : "Unable to load loyalty.");
    }
  }, [search]);

  useEffect(() => {
    void reload();
  }, [reload]);

  const onAdjust = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!adjust.accountId || !adjust.reason.trim()) return;
    setBusy(true);
    setError(null);
    try {
      await adjustLoyaltyPoints(adjust.accountId, Number(adjust.points), adjust.reason.trim());
      setAdjust({ accountId: "", points: "50", reason: "" });
      await reload();
    } catch (err) {
      setError(err instanceof AdminLoyaltyApiError ? err.message : "Adjust failed.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="min-w-0 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <AdminPageHeader
        title="Loyalty & Rewards"
        description="Points, tiers, and rewards sit above CRM and Promotion engines — discounts still resolve via promotions."
      />

      {error ? (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          {error}
        </div>
      ) : null}

      {dashboard ? (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {[
            ["Active members", dashboard.active_customers],
            ["Points issued", dashboard.points_issued],
            ["Points redeemed", dashboard.points_redeemed],
            ["Reward redemptions", dashboard.reward_redemptions],
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

      <section className="space-y-3">
        <div className="flex flex-wrap items-end gap-2">
          <h2 className="text-lg font-semibold text-zinc-900">Members</h2>
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search loyalty # / customer"
            className="admin-input ml-auto max-w-xs"
          />
          <button type="button" onClick={() => void reload()} className="admin-btn-secondary">
            Refresh
          </button>
        </div>
        <div className="admin-card overflow-hidden">
          <div className="admin-table-scroll">
            <table className="admin-table min-w-full">
              <thead>
                <tr>
                  <th>Loyalty #</th>
                  <th>Customer</th>
                  <th>Tier</th>
                  <th>Balance</th>
                  <th>Lifetime</th>
                </tr>
              </thead>
              <tbody>
                {customers.map((row) => (
                  <tr key={row.id}>
                    <td className="font-mono text-xs">{row.loyalty_number}</td>
                    <td className="admin-table-primary">{row.customer?.name ?? "—"}</td>
                    <td>{row.tier?.name ?? "—"}</td>
                    <td>{row.points_balance}</td>
                    <td>{row.lifetime_points}</td>
                  </tr>
                ))}
                {customers.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="!p-0">
                      <AdminEmptyState title="No loyalty accounts yet" />
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <form onSubmit={onAdjust} className="admin-card grid gap-3 p-4 md:grid-cols-4">
        <h2 className="md:col-span-4 text-sm font-semibold text-zinc-900">Manual point adjustment</h2>
        <select
          required
          value={adjust.accountId}
          onChange={(e) => setAdjust((a) => ({ ...a, accountId: e.target.value }))}
          className="admin-input"
        >
          <option value="">Select account</option>
          {customers.map((c) => (
            <option key={c.id} value={c.id}>
              {c.loyalty_number} · {c.customer?.name ?? "Customer"}
            </option>
          ))}
        </select>
        <input
          required
          type="number"
          value={adjust.points}
          onChange={(e) => setAdjust((a) => ({ ...a, points: e.target.value }))}
          className="admin-input"
          placeholder="Points (+/-)"
        />
        <input
          required
          value={adjust.reason}
          onChange={(e) => setAdjust((a) => ({ ...a, reason: e.target.value }))}
          className="admin-input"
          placeholder="Reason (required)"
        />
        <button type="submit" disabled={busy} className="admin-btn-primary disabled:opacity-50">
          Adjust
        </button>
      </form>

      <div className="grid gap-6 lg:grid-cols-3">
        <section className="admin-card space-y-2 p-4">
          <h2 className="text-sm font-semibold text-zinc-900">Tiers</h2>
          <ul className="space-y-1 text-sm text-zinc-600">
            {tiers.map((t) => (
              <li key={t.id} className="rounded-lg border border-zinc-200 px-2 py-1.5">
                {t.name} · min {t.min_lifetime_points} pts · ×{t.earn_multiplier}
              </li>
            ))}
          </ul>
          <button
            type="button"
            className="text-xs font-medium text-[#8b6914] hover:underline"
            onClick={async () => {
              const code = `TIER_${Date.now()}`;
              await createLoyaltyTier({
                code,
                name: `Custom ${code}`,
                sort_order: 50,
                min_lifetime_points: 100,
                is_active: true,
              });
              await reload();
            }}
          >
            + Quick tier
          </button>
        </section>

        <section className="admin-card space-y-2 p-4">
          <h2 className="text-sm font-semibold text-zinc-900">Earn rules</h2>
          <ul className="space-y-1 text-sm text-zinc-600">
            {rules.map((r) => (
              <li
                key={r.id}
                className="flex items-center justify-between rounded-lg border border-zinc-200 px-2 py-1.5"
              >
                <span>
                  {r.name} ({r.rule_type})
                </span>
                <button
                  type="button"
                  className="text-xs font-medium text-zinc-700 hover:underline"
                  onClick={async () => {
                    await updateLoyaltyRule(r.id, { is_active: !r.is_active });
                    await reload();
                  }}
                >
                  {r.is_active ? "Deactivate" : "Activate"}
                </button>
              </li>
            ))}
          </ul>
          <button
            type="button"
            className="text-xs font-medium text-[#8b6914] hover:underline"
            onClick={async () => {
              await createLoyaltyRule({
                code: `SPEND_${Date.now()}`,
                name: "Bonus spend rule",
                rule_type: "spend",
                spend_amount: 2000,
                points_awarded: 15,
                is_active: true,
                priority: 20,
              });
              await reload();
            }}
          >
            + Spend rule
          </button>
        </section>

        <section className="admin-card space-y-2 p-4">
          <h2 className="text-sm font-semibold text-zinc-900">Rewards</h2>
          <ul className="space-y-1 text-sm text-zinc-600">
            {rewards.map((r) => (
              <li
                key={r.id}
                className="flex items-center justify-between rounded-lg border border-zinc-200 px-2 py-1.5"
              >
                <span>
                  {r.name} · {r.points_cost} pts
                </span>
                <button
                  type="button"
                  className="text-xs font-medium text-zinc-700 hover:underline"
                  onClick={async () => {
                    await updateLoyaltyReward(r.id, { is_active: !r.is_active });
                    await reload();
                  }}
                >
                  {r.is_active ? "Deactivate" : "Activate"}
                </button>
              </li>
            ))}
          </ul>
          <button
            type="button"
            className="text-xs font-medium text-[#8b6914] hover:underline"
            onClick={async () => {
              await createLoyaltyReward({
                code: `RW_${Date.now()}`,
                name: "1000 TZS voucher",
                reward_type: "discount_voucher",
                points_cost: 200,
                discount_type: "fixed_amount",
                discount_value: 1000,
                is_active: true,
                channels: ["pos", "storefront"],
              });
              await reload();
            }}
          >
            + Reward
          </button>
        </section>
      </div>
    </div>
  );
}
