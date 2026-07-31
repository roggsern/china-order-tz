"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import {
  DEFAULT_MAINTENANCE_MESSAGE,
  fetchStorefrontMaintenanceStatus,
  mapMaintenanceMessage,
} from "@/lib/storefront/maintenance";

type MaintenancePageContentProps = {
  initialMessage?: string | null;
};

export function MaintenancePageContent({
  initialMessage = null,
}: MaintenancePageContentProps) {
  const [message, setMessage] = useState(
    mapMaintenanceMessage(initialMessage),
  );
  const [checking, setChecking] = useState(false);
  const [restored, setRestored] = useState(false);

  const refresh = useCallback(async () => {
    setChecking(true);
    setRestored(false);
    try {
      const status = await fetchStorefrontMaintenanceStatus();
      if (!status.maintenance) {
        setRestored(true);
        window.location.assign("/");
        return;
      }
      setMessage(mapMaintenanceMessage(status.message));
    } catch {
      setMessage(DEFAULT_MAINTENANCE_MESSAGE);
    } finally {
      setChecking(false);
    }
  }, []);

  useEffect(() => {
    if (initialMessage) {
      return;
    }
    void refresh();
  }, [initialMessage, refresh]);

  return (
    <main className="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#f7f4ec] px-4 py-16">
      <div
        className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_rgba(201,162,39,0.22),_transparent_55%),linear-gradient(180deg,_#fffdf8_0%,_#f7f4ec_100%)]"
        aria-hidden
      />
      <div className="relative w-full max-w-lg text-center">
        <p className="text-xs font-bold uppercase tracking-[0.2em] text-[#c9a227]">
          CHINA ORDER TZ
        </p>
        <h1 className="mt-4 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
          We&apos;ll be back soon
        </h1>
        <p className="mt-4 text-sm leading-relaxed text-zinc-600 sm:text-base">
          {message}
        </p>

        {restored ? (
          <p className="mt-4 text-sm text-emerald-700">Store is back — redirecting…</p>
        ) : null}

        <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
          <button
            type="button"
            onClick={() => void refresh()}
            disabled={checking}
            className="inline-flex min-w-[10rem] items-center justify-center rounded-xl bg-[#c9a227] px-5 py-3 text-sm font-bold text-zinc-950 transition hover:bg-[#e0b93a] disabled:opacity-60"
          >
            {checking ? "Checking…" : "Try again"}
          </button>
          <Link
            href="/"
            className="inline-flex min-w-[10rem] items-center justify-center rounded-xl border border-zinc-300 bg-white px-5 py-3 text-sm font-semibold text-zinc-800 transition hover:border-[#c9a227]/50 hover:text-zinc-950"
          >
            Back to home
          </Link>
        </div>
      </div>
    </main>
  );
}
