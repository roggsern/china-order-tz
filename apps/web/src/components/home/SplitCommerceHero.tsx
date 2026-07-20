import Link from "next/link";
import { CountryFlag } from "@/components/storefront/CountryFlag";
import { STOREFRONT_NAV_LABELS } from "@/lib/storefront/navigation-policy";
import { storefrontTypography } from "@/lib/storefront/typography";
import { ArrowRightIcon } from "./icons";

/**
 * Split homepage hero — one brand, two commerce journeys.
 * Stacks vertically on mobile; respects prefers-reduced-motion via CSS.
 */
export function SplitCommerceHero() {
  return (
    <section
      aria-label="Commerce journeys"
      className="relative overflow-hidden border-b border-zinc-200/80 bg-gradient-to-b from-zinc-50 via-white to-white"
    >
      <div
        className="pointer-events-none absolute inset-0 opacity-[0.35]"
        aria-hidden
        style={{
          backgroundImage:
            "radial-gradient(circle at 20% 20%, rgba(201,162,39,0.12), transparent 40%), radial-gradient(circle at 80% 30%, rgba(0,163,221,0.08), transparent 35%)",
        }}
      />

      <div className="relative mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8 lg:py-20">
        <div className="mx-auto max-w-2xl text-center">
          <p className={`${storefrontTypography.label} text-[#8b6914]`}>CHINA ORDER TZ</p>
          <h1 className={`mt-3 text-3xl sm:text-4xl lg:text-[2.75rem] ${storefrontTypography.heading}`}>
            Two ways to shop. One trusted platform.
          </h1>
          <p className={`mx-auto mt-4 max-w-xl ${storefrontTypography.body}`}>
            Import directly from China, or buy from verified Tanzanian stores — with clear
            fulfillment for each journey.
          </p>
        </div>

        <div className="mt-10 grid gap-4 sm:mt-12 sm:gap-5 lg:grid-cols-2 lg:gap-6">
          <article className="split-hero-panel group relative flex min-h-[240px] flex-col justify-between overflow-hidden rounded-3xl border border-zinc-200/80 bg-white p-7 shadow-[0_8px_30px_rgba(0,0,0,0.04)] sm:p-8">
            <div
              className="pointer-events-none absolute -right-8 -top-8 h-40 w-40 rounded-full bg-[#DE2910]/[0.06] blur-2xl motion-reduce:hidden"
              aria-hidden
            />
            <div>
              <div className="inline-flex items-center gap-2.5">
                <CountryFlag country="CN" size={22} decorative />
                <h2 className={`text-xl sm:text-2xl ${storefrontTypography.heading}`}>
                  {STOREFRONT_NAV_LABELS.orderFromChina}
                </h2>
              </div>
              <p className={`mt-3 max-w-md ${storefrontTypography.body}`}>
                Import products directly from China.
              </p>
            </div>
            <div className="mt-8">
              <Link
                href="/products?origin=china"
                className={`inline-flex min-h-11 items-center gap-2 rounded-full bg-zinc-900 px-6 py-3 text-white transition hover:bg-[#c9a227] hover:text-zinc-900 ${storefrontTypography.button}`}
              >
                Explore China Catalog
                <ArrowRightIcon className="h-4 w-4 transition group-hover:translate-x-0.5 motion-reduce:transform-none" />
              </Link>
            </div>
          </article>

          <article className="split-hero-panel group relative flex min-h-[240px] flex-col justify-between overflow-hidden rounded-3xl border border-zinc-200/80 bg-white p-7 shadow-[0_8px_30px_rgba(0,0,0,0.04)] sm:p-8">
            <div
              className="pointer-events-none absolute -right-8 -top-8 h-40 w-40 rounded-full bg-[#1EB53A]/[0.08] blur-2xl motion-reduce:hidden"
              aria-hidden
            />
            <div>
              <div className="inline-flex items-center gap-2.5">
                <CountryFlag country="TZ" size={22} decorative />
                <h2 className={`text-xl sm:text-2xl ${storefrontTypography.heading}`}>
                  {STOREFRONT_NAV_LABELS.buyFromTz}
                </h2>
              </div>
              <p className={`mt-3 max-w-md ${storefrontTypography.body}`}>
                Shop from trusted Tanzanian stores.
              </p>
            </div>
            <div className="mt-8">
              <Link
                href="/buy-from-tz"
                className={`inline-flex min-h-11 items-center gap-2 rounded-full border border-zinc-900 bg-white px-6 py-3 text-zinc-900 transition hover:border-[#c9a227] hover:bg-[#c9a227]/10 ${storefrontTypography.button}`}
              >
                Explore TZ Stores
                <ArrowRightIcon className="h-4 w-4 transition group-hover:translate-x-0.5 motion-reduce:transform-none" />
              </Link>
            </div>
          </article>
        </div>
      </div>
    </section>
  );
}
