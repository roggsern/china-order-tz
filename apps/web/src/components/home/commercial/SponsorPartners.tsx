import Link from "next/link";
import type { HomepageSectionCopy, HomepageSponsor } from "@/lib/content/homepage";

type SponsorPartnersProps = {
  sponsors: HomepageSponsor[];
  copy: HomepageSectionCopy;
};

export function SponsorPartners({ sponsors, copy }: SponsorPartnersProps) {
  if (sponsors.length === 0) {
    return null;
  }

  return (
    <section id="sponsors" className="bg-white py-16 sm:py-20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-2xl text-center">
          <p className="text-xs font-semibold uppercase tracking-[0.25em] text-[#c9a227]">
            {copy.eyebrow}
          </p>
          <h2 className="mt-3 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
            {copy.title}
          </h2>
          <p className="mt-3 text-sm leading-relaxed text-zinc-500 sm:text-base">
            {copy.description}
          </p>
        </div>

        <ul className="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 md:grid-cols-4 lg:grid-cols-5">
          {sponsors.map((sponsor) => (
            <li key={sponsor.id}>
              <Link
                href={sponsor.href}
                className="flex min-h-[88px] flex-col items-center justify-center rounded-2xl border border-zinc-200/80 bg-zinc-50 px-3 py-4 text-center transition hover:-translate-y-0.5 hover:border-[#c9a227]/40 hover:bg-white hover:shadow-[0_8px_24px_rgba(0,0,0,0.06)]"
                aria-label={`Visit ${sponsor.name}`}
              >
                <span className="text-sm font-bold tracking-wide text-zinc-800">
                  {sponsor.logoText}
                </span>
                <span className="mt-1 text-[11px] text-zinc-500">{sponsor.name}</span>
              </Link>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
