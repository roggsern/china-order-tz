import Link from "next/link";
import { ArrowRightIcon, LockIcon, ShieldIcon, ShippingIcon } from "./icons";
import { HeroLogisticsIllustration } from "./HeroLogisticsIllustration";

const trustBadges = [
  { label: "Verified Suppliers", icon: ShieldIcon },
  { label: "Secure Payments", icon: LockIcon },
  { label: "Fast Shipping", icon: ShippingIcon },
] as const;

export function Hero() {
  return (
    <section id="home" className="relative overflow-hidden bg-zinc-950">
      <div className="pointer-events-none absolute inset-0">
        <div className="hero-orb hero-orb-gold absolute -left-40 top-0 h-[28rem] w-[28rem] rounded-full bg-[#c9a227]/15 blur-3xl" />
        <div className="hero-orb absolute -right-40 bottom-0 h-96 w-96 rounded-full bg-[#c9a227]/5 blur-3xl" />
        <div
          className="absolute inset-0 opacity-[0.025]"
          style={{
            backgroundImage:
              "linear-gradient(rgba(255,255,255,.15) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.15) 1px, transparent 1px)",
            backgroundSize: "72px 72px",
          }}
        />
      </div>

      <div className="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8 lg:py-24">
        <div className="grid items-center gap-12 lg:grid-cols-2 lg:gap-16 xl:gap-20">
          <div>
            <div className="hero-fade-up mb-6 inline-flex items-center gap-2 rounded-full border border-[#c9a227]/25 bg-[#c9a227]/8 px-4 py-1.5 backdrop-blur-sm">
              <span className="h-1.5 w-1.5 rounded-full bg-[#c9a227]" />
              <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-[#e8c547]">
                Direct Import From China
              </span>
            </div>

            <h1 className="hero-fade-up hero-delay-1 text-4xl font-bold leading-[1.06] tracking-tight text-white sm:text-5xl lg:text-[3.25rem] xl:text-6xl">
              IMPORT SMARTER.
              <span className="mt-1 block bg-gradient-to-r from-[#e8c547] via-[#c9a227] to-[#f5d76e] bg-clip-text text-transparent">
                SHOP BETTER.
              </span>
            </h1>

            <p className="hero-fade-up hero-delay-2 mt-6 max-w-lg text-base leading-relaxed text-zinc-400 sm:text-lg">
              Premium products from verified Chinese suppliers — factory-direct pricing, secure
              checkout, and reliable delivery across Tanzania.
            </p>

            <div className="hero-fade-up hero-delay-3 mt-10 flex flex-col gap-3 sm:flex-row sm:items-center">
              <Link
                href="#products"
                className="group inline-flex items-center justify-center gap-2 rounded-full bg-[#c9a227] px-8 py-3.5 text-sm font-bold uppercase tracking-[0.06em] text-zinc-900 shadow-lg shadow-[#c9a227]/20 transition hover:bg-[#e8c547] hover:shadow-[#c9a227]/35"
              >
                Start Shopping
                <ArrowRightIcon className="h-4 w-4 transition group-hover:translate-x-0.5" />
              </Link>
              <Link
                href="#order-from-china"
                className="inline-flex items-center justify-center gap-2 rounded-full border border-zinc-700/80 bg-white/5 px-8 py-3.5 text-sm font-semibold text-white backdrop-blur-sm transition hover:border-[#c9a227]/40 hover:bg-white/10"
              >
                Order From China
              </Link>
            </div>

            <ul className="hero-fade-up hero-delay-4 mt-12 flex flex-wrap gap-x-6 gap-y-4 border-t border-zinc-800/80 pt-8">
              {trustBadges.map(({ label, icon: Icon }) => (
                <li key={label} className="flex items-center gap-2.5">
                  <span className="flex h-9 w-9 items-center justify-center rounded-full border border-[#c9a227]/20 bg-[#c9a227]/10 text-[#e8c547]">
                    <Icon className="h-4 w-4" />
                  </span>
                  <span className="text-sm font-medium text-zinc-300">{label}</span>
                </li>
              ))}
            </ul>
          </div>

          <div className="hero-fade-up hero-delay-2 lg:pl-4">
            <HeroLogisticsIllustration />
          </div>
        </div>
      </div>
    </section>
  );
}
