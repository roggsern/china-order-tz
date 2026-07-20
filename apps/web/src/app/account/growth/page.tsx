"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { StorefrontShell } from "@/components/layout/StorefrontShell";
import { AccountPageSkeleton } from "@/components/ui/PageSkeletons";
import { useCustomerSession } from "@/lib/customer/use-customer-session";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  CustomerGrowthApiError,
  fetchCustomerGrowthOffers,
  type CustomerGrowthOffersPayload,
} from "@/lib/api/customer-growth";

export default function AccountGrowthPage() {
  const { isReady, isLoggedIn } = useCustomerSession();
  const [data, setData] = useState<CustomerGrowthOffersPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const reload = useCallback(async () => {
    if (!getCustomerApiToken()) {
      setError("Sign in to view personalized offers.");
      setLoading(false);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      setData(await fetchCustomerGrowthOffers());
    } catch (err) {
      setError(err instanceof CustomerGrowthApiError ? err.message : "Unable to load offers.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!isReady) return;
    void reload();
  }, [isReady, isLoggedIn, reload]);

  return (
    <StorefrontShell>
      <div className="mx-auto max-w-3xl px-4 py-8">
        <div className="mb-6 flex items-center justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold text-zinc-900">Offers &amp; rewards</h1>
            <p className="mt-1 text-sm text-zinc-600">
              Personalized campaigns, benefits, and your engagement history.
            </p>
          </div>
          <Link href="/account/loyalty" className="text-sm text-zinc-700 underline">
            Loyalty
          </Link>
        </div>

        {!isReady || loading ? <AccountPageSkeleton /> : null}

        {error ? (
          <div className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
            {error}
          </div>
        ) : null}

        {data && !loading ? (
          <div className="space-y-8">
            <section className="rounded-lg border border-zinc-200 p-4">
              <h2 className="text-sm font-medium uppercase tracking-wide text-zinc-500">Benefits</h2>
              <p className="mt-2 text-sm text-zinc-800">
                Stage: <strong>{data.growth_stage ?? "new"}</strong>
              </p>
              <p className="text-sm text-zinc-800">
                Loyalty: {data.benefits?.loyalty_points ?? 0} pts
                {data.benefits?.loyalty_tier ? ` · ${data.benefits.loyalty_tier}` : ""}
              </p>
            </section>

            <section>
              <h2 className="mb-3 text-lg font-medium text-zinc-900">Personalized offers</h2>
              {data.offers.length === 0 ? (
                <p className="text-sm text-zinc-600">No active offers right now.</p>
              ) : (
                <ul className="space-y-3">
                  {data.offers.map((offer) => (
                    <li
                      key={`${offer.campaign_id}-${offer.status}`}
                      className="rounded-lg border border-zinc-200 p-4"
                    >
                      <p className="font-medium text-zinc-900">{offer.title || offer.name}</p>
                      <p className="mt-1 text-sm text-zinc-600">{offer.body}</p>
                      {offer.promotion_code ? (
                        <p className="mt-2 text-sm text-zinc-800">
                          Code: <code>{offer.promotion_code}</code>
                        </p>
                      ) : null}
                    </li>
                  ))}
                </ul>
              )}
            </section>

            <section>
              <h2 className="mb-3 text-lg font-medium text-zinc-900">Campaign history</h2>
              {data.history.length === 0 ? (
                <p className="text-sm text-zinc-600">No campaign history yet.</p>
              ) : (
                <ul className="divide-y divide-zinc-200 rounded-lg border border-zinc-200">
                  {data.history.map((item, index) => (
                    <li
                      key={`${item.campaign_id}-${index}`}
                      className="flex items-center justify-between px-4 py-3 text-sm"
                    >
                      <span className="text-zinc-800">{item.name}</span>
                      <span className="text-zinc-500">
                        {item.status}
                        {item.channel ? ` · ${item.channel}` : ""}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          </div>
        ) : null}
      </div>
    </StorefrontShell>
  );
}
