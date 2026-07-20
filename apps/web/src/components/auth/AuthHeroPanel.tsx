import type { ReactNode } from "react";
import { PremiumBrandLogo } from "@/components/branding/PremiumBrandLogo";

export type AuthHeroTrustItem = {
  label: string;
};

function WorldMapBackdrop({ idPrefix }: { idPrefix: string }) {
  return (
    <svg
      viewBox="0 0 960 600"
      className="pointer-events-none absolute inset-0 h-full w-full"
      aria-hidden
      preserveAspectRatio="xMidYMid slice"
    >
      <defs>
        <linearGradient id={`${idPrefix}-map-glow`} x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#c9a227" stopOpacity="0.45" />
          <stop offset="100%" stopColor="#e8c547" stopOpacity="0.08" />
        </linearGradient>
        <radialGradient id={`${idPrefix}-hub-glow`} cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#e8c547" stopOpacity="0.9" />
          <stop offset="100%" stopColor="#e8c547" stopOpacity="0" />
        </radialGradient>
        <pattern id={`${idPrefix}-grid`} width="32" height="32" patternUnits="userSpaceOnUse">
          <path
            d="M 32 0 L 0 0 0 32"
            fill="none"
            stroke="#c9a227"
            strokeWidth="0.35"
            opacity="0.12"
          />
        </pattern>
      </defs>

      <rect width="960" height="600" fill={`url(#${idPrefix}-grid)`} opacity="0.35" />

      <ellipse
        cx="480"
        cy="300"
        rx="360"
        ry="210"
        fill="none"
        stroke={`url(#${idPrefix}-map-glow)`}
        strokeWidth="1.2"
        opacity="0.55"
      />

      <path
        d="M140 210 C210 170, 300 155, 390 175 C470 195, 520 230, 560 265"
        fill="none"
        stroke="#c9a227"
        strokeWidth="0.9"
        opacity="0.35"
      />
      <path
        d="M560 265 C620 300, 680 340, 760 355 C820 365, 860 350, 890 320"
        fill="none"
        stroke="#c9a227"
        strokeWidth="0.9"
        opacity="0.35"
      />
      <path
        d="M620 120 C700 140, 760 180, 800 230"
        fill="none"
        stroke="#c9a227"
        strokeWidth="1"
        strokeDasharray="5 9"
        opacity="0.5"
      />
      <path
        d="M620 120 C560 200, 520 280, 500 360"
        fill="none"
        stroke="#e8c547"
        strokeWidth="0.9"
        strokeDasharray="4 8"
        opacity="0.45"
      />

      {[
        { cx: 620, cy: 120, r: 5 },
        { cx: 500, cy: 360, r: 4 },
        { cx: 650, cy: 420, r: 4 },
        { cx: 740, cy: 380, r: 3.5 },
        { cx: 390, cy: 175, r: 3.5 },
      ].map((hub) => (
        <g key={`${hub.cx}-${hub.cy}`}>
          <circle
            cx={hub.cx}
            cy={hub.cy}
            r={hub.r * 3}
            fill={`url(#${idPrefix}-hub-glow)`}
            opacity="0.35"
          />
          <circle cx={hub.cx} cy={hub.cy} r={hub.r} fill="#e8c547" opacity="0.85" />
          <circle cx={hub.cx} cy={hub.cy} r={hub.r * 0.45} fill="#fffdf5" />
        </g>
      ))}
    </svg>
  );
}

interface AuthHeroPanelProps {
  eyebrow?: string;
  title: ReactNode;
  subtitle?: string;
  trustItems?: AuthHeroTrustItem[];
  /** Extra content below trust items (e.g. benefit cards). */
  children?: ReactNode;
  footer?: ReactNode;
  idPrefix?: string;
}

const DEFAULT_TRUST: AuthHeroTrustItem[] = [
  { label: "Secure Checkout" },
  { label: "Wholesale Pricing" },
  { label: "Trusted Suppliers" },
  { label: "Fast Customer Support" },
];

export function AuthHeroPanel({
  eyebrow = "Premium Global Commerce",
  title,
  subtitle,
  trustItems = DEFAULT_TRUST,
  children,
  footer,
  idPrefix = "auth",
}: AuthHeroPanelProps) {
  return (
    <section className="relative hidden overflow-hidden bg-zinc-950 px-8 py-8 text-white lg:flex lg:flex-col lg:justify-center xl:px-12 xl:py-10">
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(201,162,39,0.18),transparent_38%),radial-gradient(circle_at_85%_80%,rgba(232,197,71,0.1),transparent_32%)]" />
      <div className="pointer-events-none absolute inset-0 opacity-[0.22]">
        <WorldMapBackdrop idPrefix={idPrefix} />
      </div>

      <div className="relative mx-auto flex w-full max-w-xl flex-col gap-8">
        <PremiumBrandLogo
          variant="lockup"
          height={56}
          align="left"
          className="w-full max-w-[15rem]"
        />

        <div>
          <p className="text-[0.7rem] font-bold uppercase tracking-[0.28em] text-[#e8c547]">
            {eyebrow}
          </p>
          <h1 className="mt-3 text-3xl font-bold leading-tight tracking-tight text-white xl:text-[2.65rem]">
            {title}
          </h1>
          {subtitle ? (
            <p className="mt-4 max-w-md text-sm leading-relaxed text-zinc-400 xl:text-[0.95rem]">
              {subtitle}
            </p>
          ) : null}
        </div>

        {trustItems.length > 0 ? (
          <ul className="grid gap-2.5 sm:grid-cols-2">
            {trustItems.map((item) => (
              <li
                key={item.label}
                className="flex items-center gap-2.5 rounded-xl border border-white/10 bg-white/[0.04] px-3.5 py-2.5 text-sm font-semibold text-zinc-200 backdrop-blur-md"
              >
                <span className="font-bold text-emerald-400" aria-hidden>
                  ✓
                </span>
                {item.label}
              </li>
            ))}
          </ul>
        ) : null}

        {children}

        {footer ? (
          <div className="border-t border-[#c9a227]/15 pt-5 text-sm leading-relaxed text-zinc-500">
            {footer}
          </div>
        ) : (
          <p className="border-t border-[#c9a227]/15 pt-5 text-sm leading-relaxed text-zinc-500">
            <span className="mr-1.5 text-[#e8c547]" aria-hidden>
              ✦
            </span>
            Trusted by shoppers across Tanzania and East Africa.
          </p>
        )}
      </div>
    </section>
  );
}
