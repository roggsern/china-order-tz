"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import {
  discountPercent,
  type HomepageFlashDeal,
  type HomepageSectionCopy,
} from "@/lib/content/homepage";
import { formatPrice } from "@/lib/catalog/utils";
import { ArrowRightIcon } from "../icons";

function useCountdown(endsAt: string) {
  const [remaining, setRemaining] = useState(() => Math.max(0, Date.parse(endsAt) - Date.now()));

  useEffect(() => {
    const tick = () => setRemaining(Math.max(0, Date.parse(endsAt) - Date.now()));
    tick();
    const id = window.setInterval(tick, 1000);
    return () => window.clearInterval(id);
  }, [endsAt]);

  const totalSec = Math.floor(remaining / 1000);
  const hours = Math.floor(totalSec / 3600);
  const minutes = Math.floor((totalSec % 3600) / 60);
  const seconds = totalSec % 60;

  return {
    expired: remaining <= 0,
    label: `${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`,
  };
}

function DealCard({ deal }: { deal: HomepageFlashDeal }) {
  const { expired, label } = useCountdown(deal.endsAt);
  const percent = discountPercent(deal.oldPrice, deal.newPrice);

  return (
    <article className="flex h-full flex-col overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
      <div className="flex aspect-[4/3] items-center justify-center bg-gradient-to-br from-[#c9a227]/10 to-zinc-100 text-5xl">
        <span aria-hidden>{deal.imageEmoji || "🔥"}</span>
      </div>
      <div className="flex flex-1 flex-col p-4 sm:p-5">
        <div className="flex items-center justify-between gap-2">
          <span className="rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-rose-700 ring-1 ring-rose-100">
            {percent > 0 ? `-${percent}%` : "Deal"}
          </span>
          <span
            className={`font-mono text-[11px] font-semibold tabular-nums ${
              expired ? "text-zinc-400" : "text-zinc-700"
            }`}
            aria-label={expired ? "Deal ended" : `Ends in ${label}`}
          >
            {expired ? "Ended" : label}
          </span>
        </div>
        <h3 className="mt-3 line-clamp-2 text-sm font-bold text-zinc-900 sm:text-base">
          {deal.title}
        </h3>
        <div className="mt-3 flex flex-wrap items-baseline gap-2">
          <span className="text-base font-bold tabular-nums text-zinc-900">
            {formatPrice(deal.newPrice)}
          </span>
          <span className="text-sm tabular-nums text-zinc-400 line-through">
            {formatPrice(deal.oldPrice)}
          </span>
        </div>
        <Link
          href={deal.href}
          className="mt-4 inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-zinc-900 px-4 text-xs font-semibold text-white transition hover:bg-[#c9a227] hover:text-zinc-900"
        >
          Shop deal
          <ArrowRightIcon className="h-3.5 w-3.5" />
        </Link>
      </div>
    </article>
  );
}

type FlashDealsProps = {
  deals: HomepageFlashDeal[];
  copy: HomepageSectionCopy;
};

export function FlashDeals({ deals, copy }: FlashDealsProps) {
  if (deals.length === 0) {
    return null;
  }

  return (
    <section id="flash-deals" className="bg-zinc-50 py-16 sm:py-20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-end">
          <div className="max-w-xl">
            <p className="text-xs font-semibold uppercase tracking-[0.25em] text-[#c9a227]">
              {copy.eyebrow}
            </p>
            <h2 className="mt-2 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
              <span aria-hidden>🔥 </span>
              {copy.title}
            </h2>
            <p className="mt-3 text-sm leading-relaxed text-zinc-500 sm:text-base">
              {copy.description}
            </p>
          </div>
          {copy.viewAllHref ? (
            <Link
              href={copy.viewAllHref}
              className="inline-flex items-center gap-2 text-sm font-semibold text-zinc-800 transition hover:text-[#c9a227]"
            >
              {copy.viewAllLabel || "View all"}
              <ArrowRightIcon className="h-4 w-4" />
            </Link>
          ) : null}
        </div>

        <div className="mt-10 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
          {deals.map((deal) => (
            <DealCard key={deal.id} deal={deal} />
          ))}
        </div>
      </div>
    </section>
  );
}
