"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { StorefrontShell } from "@/components/layout/StorefrontShell";
import { AccountPageSkeleton } from "@/components/ui/PageSkeletons";
import { useCustomerSession } from "@/lib/customer/use-customer-session";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  CustomerLoyaltyApiError,
  fetchCustomerLoyaltyProfile,
  fetchCustomerLoyaltyRewards,
  fetchCustomerLoyaltyTransactions,
  redeemCustomerLoyaltyReward,
  type CustomerLoyaltyProfile,
  type CustomerLoyaltyReward,
  type CustomerLoyaltyTransaction,
} from "@/lib/api/customer-loyalty";

export default function AccountLoyaltyPage() {
  const { isReady, isLoggedIn } = useCustomerSession();
  const [profile, setProfile] = useState<CustomerLoyaltyProfile | null>(null);
  const [transactions, setTransactions] = useState<CustomerLoyaltyTransaction[]>([]);
  const [rewards, setRewards] = useState<CustomerLoyaltyReward[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const reload = useCallback(async () => {
    if (!getCustomerApiToken()) {
      setError("Sign in to view your loyalty rewards.");
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const [p, t, r] = await Promise.all([
        fetchCustomerLoyaltyProfile(),
        fetchCustomerLoyaltyTransactions(),
        fetchCustomerLoyaltyRewards(),
      ]);
      setProfile(p);
      setTransactions(t);
      setRewards(Array.isArray(r) ? r : []);
    } catch (err) {
      setError(err instanceof CustomerLoyaltyApiError ? err.message : "Unable to load loyalty.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!isReady) return;
    void reload();
  }, [isReady, isLoggedIn, reload]);

  const onRedeem = async (rewardId: string) => {
    setBusy(true);
    setMessage(null);
    setError(null);
    try {
      const result = await redeemCustomerLoyaltyReward(rewardId);
      setMessage(
        result.promotion_code
          ? `Redeemed. Use code ${result.promotion_code} at checkout.`
          : "Reward redeemed.",
      );
      await reload();
    } catch (err) {
      setError(err instanceof CustomerLoyaltyApiError ? err.message : "Redeem failed.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <StorefrontShell>
      <div className="mx-auto max-w-3xl px-4 py-8">
        <div className="mb-6 flex items-center justify-between gap-3">
          <div>
            <p className="text-sm text-zinc-500">
              <Link href="/account" className="hover:underline">
                Account
              </Link>{" "}
              / Loyalty
            </p>
            <h1 className="mt-1 text-2xl font-semibold text-zinc-900">Loyalty & Rewards</h1>
          </div>
        </div>

        {!isReady || loading ? <AccountPageSkeleton /> : null}

        {error ? (
          <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {error}
          </div>
        ) : null}
        {message ? (
          <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            {message}
          </div>
        ) : null}

        {profile ? (
          <div className="space-y-6">
            <div className="rounded-lg border border-zinc-200 bg-white p-5">
              <p className="text-xs uppercase tracking-wide text-zinc-500">Loyalty number</p>
              <p className="mt-1 font-mono text-lg text-zinc-900">{profile.loyalty_number}</p>
              <div className="mt-4 grid grid-cols-3 gap-3 text-sm">
                <div>
                  <p className="text-zinc-500">Points</p>
                  <p className="text-xl font-semibold">{profile.points_balance}</p>
                </div>
                <div>
                  <p className="text-zinc-500">Lifetime</p>
                  <p className="text-xl font-semibold">{profile.lifetime_points}</p>
                </div>
                <div>
                  <p className="text-zinc-500">Tier</p>
                  <p className="text-xl font-semibold">{profile.tier?.name ?? "Bronze"}</p>
                </div>
              </div>
            </div>

            <section>
              <h2 className="mb-2 text-lg font-medium text-zinc-900">Available rewards</h2>
              <ul className="space-y-2">
                {rewards.map((reward) => (
                  <li
                    key={reward.id}
                    className="flex items-center justify-between rounded-md border border-zinc-200 px-3 py-2"
                  >
                    <div>
                      <p className="font-medium text-zinc-900">{reward.name}</p>
                      <p className="text-xs text-zinc-500">{reward.points_cost} points</p>
                    </div>
                    <button
                      type="button"
                      disabled={busy || profile.points_balance < reward.points_cost}
                      onClick={() => void onRedeem(reward.id)}
                      className="rounded-md bg-zinc-900 px-3 py-1.5 text-sm text-white disabled:opacity-40"
                    >
                      Redeem
                    </button>
                  </li>
                ))}
                {rewards.length === 0 ? (
                  <li className="text-sm text-zinc-500">No active rewards right now.</li>
                ) : null}
              </ul>
            </section>

            <section>
              <h2 className="mb-2 text-lg font-medium text-zinc-900">Points timeline</h2>
              <ul className="divide-y divide-zinc-100 rounded-md border border-zinc-200">
                {transactions.map((tx) => (
                  <li key={tx.id} className="flex justify-between px-3 py-2 text-sm">
                    <div>
                      <p className="font-medium capitalize text-zinc-800">{tx.entry_type}</p>
                      <p className="text-xs text-zinc-500">{tx.reason}</p>
                    </div>
                    <p className={tx.points >= 0 ? "text-emerald-700" : "text-red-700"}>
                      {tx.points >= 0 ? "+" : ""}
                      {tx.points}
                    </p>
                  </li>
                ))}
                {transactions.length === 0 ? (
                  <li className="px-3 py-4 text-sm text-zinc-500">No points activity yet.</li>
                ) : null}
              </ul>
            </section>
          </div>
        ) : null}
      </div>
    </StorefrontShell>
  );
}
