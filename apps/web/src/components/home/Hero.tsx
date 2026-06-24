import Link from "next/link";
import { ArrowRightIcon } from "./icons";

const floatingCards = [
  { emoji: "📦", label: "Fast Delivery", delay: "0s", position: "left-0 top-8" },
  { emoji: "🏭", label: "Factory Direct", delay: "0.5s", position: "right-0 top-0" },
  { emoji: "✈️", label: "Air & Sea Freight", delay: "1s", position: "left-4 bottom-16" },
  { emoji: "🛍️", label: "2M+ Products", delay: "1.5s", position: "right-4 bottom-8" },
];

export function Hero() {
  return (
    <section id="home" className="relative overflow-hidden bg-zinc-950">
      <div className="pointer-events-none absolute inset-0">
        <div className="hero-orb hero-orb-gold absolute -left-32 top-0 h-96 w-96 rounded-full bg-[#c9a227]/20 blur-3xl" />
        <div className="hero-orb hero-orb-red absolute -right-32 bottom-0 h-96 w-96 rounded-full bg-red-600/10 blur-3xl" />
        <div className="hero-orb hero-orb-gold absolute left-1/2 top-1/2 h-64 w-64 -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#c9a227]/5 blur-3xl" />
        <div
          className="absolute inset-0 opacity-[0.03]"
          style={{
            backgroundImage:
              "linear-gradient(rgba(255,255,255,.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.1) 1px, transparent 1px)",
            backgroundSize: "64px 64px",
          }}
        />
      </div>

      <div className="relative mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8 lg:py-32">
        <div className="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
          <div className="hero-fade-up">
            <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-[#c9a227]/30 bg-[#c9a227]/10 px-4 py-1.5 backdrop-blur-sm">
              <span className="h-2 w-2 animate-pulse rounded-full bg-[#c9a227]" />
              <span className="text-xs font-semibold uppercase tracking-widest text-[#e8c547]">
                Direct from China to Tanzania
              </span>
            </div>

            <h1 className="text-4xl font-bold leading-[1.08] tracking-tight text-white sm:text-5xl lg:text-6xl">
              <span className="hero-fade-up hero-delay-1 block">IMPORT PRODUCTS</span>{" "}
              <span className="hero-fade-up hero-delay-2 bg-gradient-to-r from-[#e8c547] via-[#c9a227] to-[#f5d76e] bg-clip-text text-transparent">
                DIRECTLY FROM CHINA
              </span>
            </h1>

            <p className="hero-fade-up hero-delay-3 mt-6 text-lg font-medium tracking-wide text-zinc-400 sm:text-xl">
              Fast <span className="text-[#c9a227]">•</span> Trusted{" "}
              <span className="text-[#c9a227]">•</span> Affordable
            </p>

            <p className="hero-fade-up hero-delay-4 mt-4 max-w-lg text-base leading-relaxed text-zinc-500">
              Shop millions of products from verified Chinese suppliers. Factory-direct pricing,
              secure checkout, and reliable delivery to your doorstep in Tanzania.
            </p>

            <div className="hero-fade-up hero-delay-5 mt-10 flex flex-col gap-3 sm:flex-row sm:items-center">
              <Link
                href="#products"
                className="group inline-flex items-center justify-center gap-2 rounded-full bg-[#c9a227] px-8 py-3.5 text-sm font-bold uppercase tracking-wide text-zinc-900 shadow-lg shadow-[#c9a227]/25 transition hover:bg-[#e8c547] hover:shadow-[#c9a227]/40"
              >
                Start Shopping
                <ArrowRightIcon className="h-4 w-4 transition group-hover:translate-x-0.5" />
              </Link>
              <Link
                href="#order-from-china"
                className="inline-flex items-center justify-center gap-2 rounded-full border border-zinc-700 bg-zinc-900/50 px-8 py-3.5 text-sm font-semibold text-white backdrop-blur transition hover:border-[#c9a227]/50 hover:bg-zinc-800"
              >
                Order From China
              </Link>
            </div>

            <dl className="hero-fade-up hero-delay-6 mt-12 grid grid-cols-3 gap-4 border-t border-zinc-800 pt-8 sm:gap-8">
              {[
                { value: "2M+", label: "Products" },
                { value: "50K+", label: "Happy Customers" },
                { value: "7–14", label: "Day Shipping" },
              ].map((stat) => (
                <div key={stat.label}>
                  <dt className="text-2xl font-bold text-white sm:text-3xl">{stat.value}</dt>
                  <dd className="mt-1 text-xs text-zinc-500 sm:text-sm">{stat.label}</dd>
                </div>
              ))}
            </dl>
          </div>

          <div className="relative mx-auto w-full max-w-lg lg:mx-0">
            <div className="hero-fade-up hero-delay-3 relative aspect-square">
              <div className="absolute inset-4 rounded-3xl bg-gradient-to-br from-[#c9a227]/20 to-red-600/10 blur-2xl" />

              {floatingCards.map((card) => (
                <div
                  key={card.label}
                  className={`hero-float absolute ${card.position} z-10 flex items-center gap-2 rounded-2xl border border-zinc-700/60 bg-zinc-900/90 px-4 py-3 shadow-xl backdrop-blur-md`}
                  style={{ animationDelay: card.delay }}
                >
                  <span className="text-2xl">{card.emoji}</span>
                  <span className="text-xs font-semibold text-zinc-300">{card.label}</span>
                </div>
              ))}

              <div className="relative flex h-full flex-col justify-between rounded-3xl border border-zinc-800 bg-zinc-900/80 p-6 backdrop-blur sm:p-8">
                <div className="flex items-start justify-between">
                  <span className="rounded-full bg-red-600 px-3 py-1 text-xs font-bold uppercase text-white">
                    Limited Offer
                  </span>
                  <span className="text-sm font-medium text-[#e8c547]">Up to 70% off</span>
                </div>

                <div className="flex flex-1 items-center justify-center py-6">
                  <div className="relative h-44 w-44 sm:h-52 sm:w-52">
                    <div className="absolute inset-0 rounded-full border border-[#c9a227]/20" />
                    <div className="hero-spin-slow absolute inset-4 rounded-full border border-dashed border-zinc-700/50" />
                    <div className="absolute inset-0 flex items-center justify-center">
                      <div className="flex h-24 w-24 items-center justify-center rounded-2xl bg-gradient-to-br from-[#c9a227] to-[#8b6914] text-4xl shadow-lg shadow-[#c9a227]/30 sm:h-28 sm:w-28 sm:text-5xl">
                        🇨🇳
                      </div>
                    </div>
                    {["📱", "👗", "🛋️", "🔧"].map((emoji, i) => {
                      const angle = (i * 90 * Math.PI) / 180;
                      const radius = 38;
                      const x = 50 + radius * Math.cos(angle - Math.PI / 2);
                      const y = 50 + radius * Math.sin(angle - Math.PI / 2);
                      return (
                        <div
                          key={emoji}
                          className="hero-orbit absolute flex h-10 w-10 items-center justify-center rounded-xl border border-zinc-700/50 bg-zinc-800/80 text-lg backdrop-blur sm:h-11 sm:w-11 sm:text-xl"
                          style={{
                            left: `${x}%`,
                            top: `${y}%`,
                            transform: "translate(-50%, -50%)",
                            animationDelay: `${i * 0.25}s`,
                          }}
                        >
                          {emoji}
                        </div>
                      );
                    })}
                  </div>
                </div>

                <div className="rounded-2xl border border-[#c9a227]/20 bg-[#c9a227]/5 p-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-[#e8c547]">Flash deals ending soon</p>
                      <p className="mt-1 text-xs text-zinc-500">
                        Electronics, fashion & furniture — imported from Shenzhen
                      </p>
                    </div>
                    <Link
                      href="#products"
                      className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#c9a227] text-zinc-900 transition hover:bg-[#e8c547]"
                      aria-label="View deals"
                    >
                      <ArrowRightIcon className="h-4 w-4" />
                    </Link>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
