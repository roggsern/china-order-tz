import Link from "next/link";
import type { HomepageCollection, HomepageSectionCopy } from "@/lib/content/homepage";
import { ArrowRightIcon } from "../icons";

type FeaturedCollectionsProps = {
  collections: HomepageCollection[];
  copy: HomepageSectionCopy;
};

export function FeaturedCollections({ collections, copy }: FeaturedCollectionsProps) {
  if (collections.length === 0) {
    return null;
  }

  return (
    <section id="collections" className="bg-white py-16 sm:py-20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-end">
          <div className="max-w-xl">
            <p className="text-xs font-semibold uppercase tracking-[0.25em] text-[#c9a227]">
              {copy.eyebrow}
            </p>
            <h2 className="mt-2 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
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

        <ul className="mt-10 grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-6">
          {collections.map((collection) => (
            <li key={collection.id}>
              <Link
                href={collection.href}
                className={`group relative flex h-full min-h-[220px] flex-col overflow-hidden rounded-2xl bg-gradient-to-br ${collection.gradient} p-5 shadow-[0_8px_24px_rgba(0,0,0,0.08)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_16px_36px_rgba(0,0,0,0.14)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#c9a227] focus-visible:ring-offset-2 sm:min-h-[240px] sm:p-5`}
              >
                <div
                  className="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/80 via-black/35 to-black/10 transition duration-300 group-hover:from-black/85 group-hover:via-black/40"
                  aria-hidden
                />

                <div className="relative flex flex-1 flex-col">
                  <span
                    className="flex h-12 w-12 items-center justify-center rounded-xl border border-white/25 bg-white/15 text-2xl shadow-sm backdrop-blur-md sm:text-[1.75rem]"
                    aria-hidden
                  >
                    {collection.icon}
                  </span>

                  <div className="mt-5 flex flex-1 flex-col">
                    <h3 className="text-base font-bold leading-snug tracking-tight text-white drop-shadow-sm sm:text-lg">
                      {collection.name}
                    </h3>
                    <p className="mt-2 line-clamp-2 text-xs leading-relaxed text-white/80 sm:text-[13px]">
                      {collection.description}
                    </p>

                    <span className="mt-auto inline-flex items-center gap-1.5 pt-5 text-[11px] font-bold uppercase tracking-[0.14em] text-[#e8c547] transition duration-300 group-hover:gap-2.5 sm:text-xs">
                      Explore collection
                      <ArrowRightIcon className="h-3.5 w-3.5 transition duration-300 group-hover:translate-x-0.5" />
                    </span>
                  </div>
                </div>

                <div
                  className="pointer-events-none absolute inset-x-0 bottom-0 h-0.5 bg-gradient-to-r from-transparent via-[#c9a227] to-transparent opacity-70 transition duration-300 group-hover:opacity-100"
                  aria-hidden
                />
              </Link>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
