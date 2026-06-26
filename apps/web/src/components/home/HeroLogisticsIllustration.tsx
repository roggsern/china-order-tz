"use client";

import { motion, useReducedMotion } from "framer-motion";
import { AnimatedPlane, AnimatedShip } from "@/components/branding/AnimatedPlane";
import { ShippingRoute } from "@/components/branding/ShippingRoute";
import { BRAND_COLORS, EASE_PREMIUM } from "@/components/branding/constants";

const floatingCards = [
  {
    label: "Air Cargo",
    detail: "3–7 day express",
    icon: "✈️",
    position: "left-[4%] top-[8%]",
    delay: 0,
  },
  {
    label: "Sea Freight",
    detail: "Bulk containers",
    icon: "🚢",
    position: "right-[2%] top-[18%]",
    delay: 0.15,
  },
  {
    label: "Warehouse",
    detail: "Dar es Salaam hub",
    icon: "🏭",
    position: "left-[6%] bottom-[22%]",
    delay: 0.3,
  },
  {
    label: "Tracking",
    detail: "Real-time updates",
    icon: "📦",
    position: "right-[5%] bottom-[12%]",
    delay: 0.45,
  },
] as const;

export function HeroLogisticsIllustration() {
  const reduceMotion = useReducedMotion();

  const fadeIn = (delay: number) =>
    reduceMotion
      ? { initial: { opacity: 1 }, animate: { opacity: 1 } }
      : {
          initial: { opacity: 0, y: 16 },
          animate: { opacity: 1, y: 0 },
          transition: { delay, duration: 0.7, ease: EASE_PREMIUM },
        };

  return (
    <div className="relative mx-auto aspect-[4/3] w-full max-w-xl lg:max-w-none">
      <div className="pointer-events-none absolute inset-0 rounded-[2rem] bg-gradient-to-br from-[#c9a227]/10 via-transparent to-zinc-800/40 blur-2xl" />

      <div className="relative h-full overflow-hidden rounded-[2rem] border border-white/10 bg-gradient-to-br from-zinc-900 via-zinc-950 to-black shadow-2xl shadow-black/50">
        <div
          className="absolute inset-0 opacity-[0.04]"
          style={{
            backgroundImage:
              "linear-gradient(rgba(255,255,255,.12) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.12) 1px, transparent 1px)",
            backgroundSize: "48px 48px",
          }}
        />

        <svg
          viewBox="0 0 480 360"
          fill="none"
          className="absolute inset-0 h-full w-full"
          aria-hidden
        >
          <defs>
            <linearGradient id="hero-ocean" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="#0f172a" />
              <stop offset="100%" stopColor="#020617" />
            </linearGradient>
            <linearGradient id="hero-gold-glow" x1="0%" y1="0%" x2="100%" y2="0%">
              <stop offset="0%" stopColor={BRAND_COLORS.goldDark} stopOpacity={0.3} />
              <stop offset="50%" stopColor={BRAND_COLORS.goldLight} stopOpacity={0.9} />
              <stop offset="100%" stopColor={BRAND_COLORS.gold} stopOpacity={0.4} />
            </linearGradient>
          </defs>

          <rect width="480" height="360" fill="url(#hero-ocean)" />

          {/* Ocean waves */}
          <path
            d="M0 260 Q60 250 120 260 T240 260 T360 260 T480 260 V360 H0 Z"
            fill="#0c1222"
            fillOpacity={0.8}
          />
          <path
            d="M0 275 Q80 268 160 275 T320 275 T480 275"
            stroke={BRAND_COLORS.gold}
            strokeOpacity={0.15}
            strokeWidth="1.5"
            fill="none"
          />

          {/* China landmark silhouette */}
          <g opacity={0.9}>
            <rect x="32" y="140" width="8" height="80" rx="1" fill="#334155" />
            <rect x="44" y="120" width="10" height="100" rx="1" fill="#475569" />
            <rect x="58" y="155" width="7" height="65" rx="1" fill="#334155" />
            <polygon points="48,118 52,108 56,118" fill="#64748b" />
            <circle cx="40" cy="108" r="14" fill={BRAND_COLORS.chinaRed} fillOpacity={0.85} />
            <circle cx="40" cy="108" r="10" fill="none" stroke="#fcd116" strokeWidth="1.5" />
          </g>

          {/* Tanzania landmark */}
          <g opacity={0.9}>
            <rect x="400" y="165" width="48" height="55" rx="2" fill="#1e293b" />
            <polygon points="424,165 424,145 428,155" fill="#334155" />
            <rect x="410" y="175" width="28" height="4" rx="1" fill={BRAND_COLORS.tzGreen} fillOpacity={0.7} />
            <rect x="410" y="183" width="28" height="4" rx="1" fill={BRAND_COLORS.tzYellow} fillOpacity={0.7} />
            <rect x="410" y="191" width="28" height="4" rx="1" fill={BRAND_COLORS.tzBlue} fillOpacity={0.7} />
          </g>

          {/* Containers on ship deck */}
          <rect x="175" y="228" width="18" height="14" rx="1" fill={BRAND_COLORS.goldDark} fillOpacity={0.6} />
          <rect x="196" y="224" width="18" height="18" rx="1" fill={BRAND_COLORS.gold} fillOpacity={0.55} />
          <rect x="217" y="228" width="18" height="14" rx="1" fill={BRAND_COLORS.goldLight} fillOpacity={0.45} />
          <rect x="238" y="226" width="16" height="16" rx="1" fill={BRAND_COLORS.goldDark} fillOpacity={0.5} />

          {/* Warehouse */}
          <rect x="355" y="200" width="70" height="40" rx="2" fill="#1e293b" stroke="#334155" strokeWidth="1" />
          <rect x="365" y="210" width="14" height="18" rx="1" fill={BRAND_COLORS.gold} fillOpacity={0.25} />
          <rect x="385" y="210" width="14" height="18" rx="1" fill={BRAND_COLORS.gold} fillOpacity={0.25} />
          <rect x="405" y="210" width="14" height="18" rx="1" fill={BRAND_COLORS.gold} fillOpacity={0.25} />
          <polygon points="355,200 390,182 425,200" fill="#334155" />

          {/* Country labels */}
          <text x="40" y="238" fill="#94a3b8" fontSize="11" fontWeight="600" letterSpacing="0.08em">
            CHINA
          </text>
          <text x="392" y="255" fill="#94a3b8" fontSize="11" fontWeight="600" letterSpacing="0.08em">
            TANZANIA
          </text>
        </svg>

        {/* Animated route overlay */}
        <div className="absolute inset-0 flex items-center justify-center">
          <div className="relative h-[55%] w-[85%]">
            <ShippingRoute className="absolute inset-0 h-full w-full" />
            <AnimatedPlane className="absolute inset-0 h-full w-full" />
            <AnimatedShip className="absolute inset-0 h-full w-full" />
          </div>
        </div>

        {/* Glass floating cards */}
        {floatingCards.map((card) => (
          <motion.div
            key={card.label}
            {...fadeIn(0.6 + card.delay)}
            className={`hero-float absolute z-10 ${card.position}`}
          >
            <div className="flex items-center gap-2.5 rounded-2xl border border-white/15 bg-white/10 px-3.5 py-2.5 shadow-xl backdrop-blur-md">
              <span className="text-lg">{card.icon}</span>
              <div>
                <p className="text-[11px] font-semibold text-white">{card.label}</p>
                <p className="text-[10px] text-zinc-400">{card.detail}</p>
              </div>
            </div>
          </motion.div>
        ))}

        {/* Bottom status bar */}
        <motion.div
          {...fadeIn(1.1)}
          className="absolute bottom-0 left-0 right-0 border-t border-white/10 bg-black/40 px-5 py-3 backdrop-blur-md"
        >
          <div className="flex items-center justify-between gap-4">
            <div className="flex items-center gap-2">
              <span className="relative flex h-2 w-2">
                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#c9a227] opacity-60" />
                <span className="relative inline-flex h-2 w-2 rounded-full bg-[#c9a227]" />
              </span>
              <span className="text-[11px] font-medium text-zinc-300">Live route · Shanghai → Dar</span>
            </div>
            <span className="text-[11px] font-semibold text-[#e8c547]">7–14 days</span>
          </div>
        </motion.div>
      </div>
    </div>
  );
}
