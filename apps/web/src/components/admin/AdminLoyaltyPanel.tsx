"use client";

import { useCallback, useEffect, useState } from "react";
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
    <div className="space-y-6 p-4 md:p-6">
      <div>
        <h1 className="text-xl font-semibold text-zinc-50">Loyalty & Rewards</h1>
        <p className="mt-1 text-sm text-zinc-500">
          Points, tiers, and rewards sit above CRM and Promotion engines — discounts still resolve via
          promotions.
        </p>
      </div>

      {error ? (
        <div className="rounded-md border border-red-900/50 bg-red-950/30 px-3 py-2 text-sm text-red-200">
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
            <div key={String(label)} className="rounded-lg border border-zinc-800 bg-zinc-900/40 p-4">
              <p className="text-xs uppercase tracking-wide text-zinc-500">{label}</p>
              <p className="mt-1 text-2xl font-semibold text-zinc-50">{value}</p>
            </div>
          ))}
        </div>
      ) : null}

      <section className="space-y-3">
        <div className="flex flex-wrap items-end gap-2">
          <h2 className="text-lg font-medium text-zinc-100">Members</h2>
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search loyalty # / customer"
            className="ml-auto rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm text-zinc-100"
          />
          <button
            type="button"
            onClick={() => void reload()}
            className="rounded-md border border-zinc-700 px-3 py-1.5 text-sm text-zinc-200"
          >
            Refresh
          </button>
        </div>
        <div className="overflow-x-auto rounded-lg border border-zinc-800">
          <table className="min-w-full text-left text-sm text-zinc-300">
            <thead className="bg-zinc-900/80 text-xs uppercase text-zinc-500">
              <tr>
                <th className="px-3 py-2">Loyalty #</th>
                <th className="px-3 py-2">Customer</th>
                <th className="px-3 py-2">Tier</th>
                <th className="px-3 py-2">Balance</th>
                <th className="px-3 py-2">Lifetime</th>
              </tr>
            </thead>
            <tbody>
              {customers.map((row) => (
                <tr key={row.id} className="border-t border-zinc-800">
                  <td className="px-3 py-2 font-mono text-xs">{row.loyalty_number}</td>
                  <td className="px-3 py-2">{row.customer?.name ?? "—"}</td>
                  <td className="px-3 py-2">{row.tier?.name ?? "—"}</td>
                  <td className="px-3 py-2">{row.points_balance}</td>
                  <td className="px-3 py-2">{row.lifetime_points}</td>
                </tr>
              ))}
              {customers.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-3 py-6 text-center text-zinc-500">
                    No loyalty accounts yet.
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </section>

      <form
        onSubmit={onAdjust}
        className="grid gap-3 rounded-lg border border-zinc-800 bg-zinc-900/40 p-4 md:grid-cols-4"
      >
        <h2 className="md:col-span-4 text-sm font-medium text-zinc-200">Manual point adjustment</h2>
        <select
          required
          value={adjust.accountId}
          onChange={(e) => setAdjust((a) => ({ ...a, accountId: e.target.value }))}
          className="rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm text-zinc-100"
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
          className="rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm text-zinc-100"
          placeholder="Points (+/-)"
        />
        <input
          required
          value={adjust.reason}
          onChange={(e) => setAdjust((a) => ({ ...a, reason: e.target.value }))}
          className="rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm text-zinc-100"
          placeholder="Reason (required)"
        />
        <button
          type="submit"
          disabled={busy}
          className="rounded-md bg-[#c9a227] px-3 py-1.5 text-sm font-medium text-zinc-950 disabled:opacity-50"
        >
          Adjust
        </button>
      </form>

      <div className="grid gap-6 lg:grid-cols-3">
        <section className="space-y-2">
          <h2 className="text-sm font-medium text-zinc-200">Tiers</h2>
          <ul className="space-y-1 text-sm text-zinc-400">
            {tiers.map((t) => (
              <li key={t.id} className="rounded border border-zinc-800 px-2 py-1.5">
                {t.name} · min {t.min_lifetime_points} pts · ×{t.earn_multiplier}
              </li>
            ))}
          </ul>
          <button
            type="button"
            className="text-xs text-[#c9a227]"
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

        <section className="space-y-2">
          <h2 className="text-sm font-medium text-zinc-200">Earn rules</h2>
          <ul className="space-y-1 text-sm text-zinc-400">
            {rules.map((r) => (
              <li key={r.id} className="flex items-center justify-between rounded border border-zinc-800 px-2 py-1.5">
                <span>
                  {r.name} ({r.rule_type})
                </span>
                <button
                  type="button"
                  className="text-xs text-zinc-300"
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
            className="text-xs text-[#c9a227]"
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

        <section className="space-y-2">
          <h2 className="text-sm font-medium text-zinc-200">Rewards</h2>
          <ul className="space-y-1 text-sm text-zinc-400">
            {rewards.map((r) => (
              <li key={r.id} className="flex items-center justify-between rounded border border-zinc-800 px-2 py-1.5">
                <span>
                  {r.name} · {r.points_cost} pts
                </span>
                <button
                  type="button"
                  className="text-xs text-zinc-300"
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
            className="text-xs text-[#c9a227]"
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
