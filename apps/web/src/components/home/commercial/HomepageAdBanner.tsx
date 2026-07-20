import Link from "next/link";
import type { HomepageAdvertisement } from "@/lib/content/homepage";
import { ArrowRightIcon } from "../icons";

type HomepageAdBannerProps = {
  ad: HomepageAdvertisement;
  compact?: boolean;
};

export function HomepageAdBanner({ ad, compact = false }: HomepageAdBannerProps) {
  return (
    <aside
      className={`relative overflow-hidden rounded-2xl ${
        compact ? "p-5 sm:p-6" : "p-6 sm:p-8"
      } ${ad.backgroundClass || "bg-zinc-900"}`}
      aria-label={ad.sponsorName ? `Advertisement by ${ad.sponsorName}` : "Advertisement"}
    >
      <div className="relative z-[1] flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div className="max-w-xl">
          {ad.sponsorName || ad.subtitle ? (
            <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/70">
              {[ad.sponsorName, ad.subtitle].filter(Boolean).join(" · ")}
            </p>
          ) : null}
          <h3
            className={`mt-1 font-bold tracking-tight text-white ${
              compact ? "text-lg sm:text-xl" : "text-xl sm:text-2xl"
            }`}
          >
            {ad.title}
          </h3>
          {ad.description ? (
            <p className="mt-2 text-sm leading-relaxed text-white/80">{ad.description}</p>
          ) : null}
        </div>
        <Link
          href={ad.targetUrl}
          className="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-zinc-900 transition hover:bg-[#e8c547]"
        >
          {ad.ctaLabel}
          <ArrowRightIcon className="h-4 w-4" />
        </Link>
      </div>
    </aside>
  );
}

type HomepageAdRailProps = {
  ads: HomepageAdvertisement[];
  className?: string;
  compact?: boolean;
};

export function HomepageAdRail({ ads, className = "", compact }: HomepageAdRailProps) {
  if (ads.length === 0) {
    return null;
  }

  return (
    <div className={`space-y-4 ${className}`}>
      {ads.map((ad) => (
        <HomepageAdBanner key={ad.id} ad={ad} compact={compact} />
      ))}
    </div>
  );
}
