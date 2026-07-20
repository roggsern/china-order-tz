import type { ReactNode } from "react";
import Link from "next/link";
import { PremiumBrandLogo } from "@/components/branding/PremiumBrandLogo";
import { AuthHeroPanel, type AuthHeroTrustItem } from "./AuthHeroPanel";

interface AuthSplitLayoutProps {
  hero: {
    eyebrow?: string;
    title: ReactNode;
    subtitle?: string;
    trustItems?: AuthHeroTrustItem[];
    children?: ReactNode;
    footer?: ReactNode;
    idPrefix?: string;
  };
  /** Form card header */
  card: {
    eyebrow?: string;
    title: string;
    description?: string;
  };
  children: ReactNode;
  /** Optional content below the card (mobile benefits, etc.) */
  belowCard?: ReactNode;
}

export function AuthSplitLayout({ hero, card, children, belowCard }: AuthSplitLayoutProps) {
  return (
    <div className="min-h-screen overflow-x-hidden bg-zinc-950 lg:grid lg:grid-cols-2">
      <AuthHeroPanel
        eyebrow={hero.eyebrow}
        title={hero.title}
        subtitle={hero.subtitle}
        trustItems={hero.trustItems}
        footer={hero.footer}
        idPrefix={hero.idPrefix}
      >
        {hero.children}
      </AuthHeroPanel>

      <div className="relative flex min-w-0 flex-col">
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(201,162,39,0.06),transparent_42%)] lg:bg-[radial-gradient(circle_at_top_right,rgba(201,162,39,0.08),transparent_40%)]" />

        <div className="relative flex flex-1 justify-center px-4 py-6 sm:px-6 sm:py-8 lg:items-center lg:px-10 lg:py-8 xl:px-14">
          <div className="w-full min-w-0 max-w-lg animate-fade-in">
            <div className="mb-5 flex justify-center lg:hidden">
              <PremiumBrandLogo
                variant="lockup"
                height={40}
                align="center"
                className="w-full max-w-[10.5rem] sm:max-w-[12rem]"
              />
            </div>

            <div className="rounded-3xl border border-zinc-800/90 bg-zinc-900/75 p-5 shadow-[0_20px_60px_rgba(0,0,0,0.45)] backdrop-blur-md sm:p-7 lg:p-8">
              <div className="mb-6 text-center sm:mb-7">
                {card.eyebrow ? (
                  <p className="text-[0.7rem] font-bold uppercase tracking-[0.24em] text-[#e8c547]">
                    {card.eyebrow}
                  </p>
                ) : null}
                <h1
                  className={`text-xl font-bold tracking-tight text-white sm:text-2xl ${
                    card.eyebrow ? "mt-2" : ""
                  }`}
                >
                  {card.title}
                </h1>
                {card.description ? (
                  <p className="mt-2 text-sm leading-relaxed text-zinc-400">{card.description}</p>
                ) : null}
              </div>

              {children}
            </div>

            {belowCard}

            <p className="mt-6 text-center">
              <Link
                href="/"
                className="text-sm text-zinc-500 transition hover:text-zinc-300 focus:outline-none focus-visible:underline"
              >
                ← Back to storefront
              </Link>
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
