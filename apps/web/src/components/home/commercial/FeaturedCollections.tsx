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
                className={`group flex h-full flex-col overflow-hidden rounded-2xl border border-zinc-200/80 bg-gradient-to-br ${collection.gradient} p-4 transition hover:-translate-y-0.5 hover:border-[#c9a227]/35 hover:shadow-[0_10px_28px_rgba(0,0,0,0.06)]`}
              >
                <span className="text-3xl" aria-hidden>
                  {collection.icon}
                </span>
                <h3 className="mt-4 text-sm font-bold text-zinc-900 group-hover:text-[#8b6914]">
                  {collection.name}
                </h3>
                <p className="mt-1 line-clamp-2 text-xs leading-relaxed text-zinc-500">
                  {collection.description}
                </p>
              </Link>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
