"use client";

import { useState } from "react";
import Link from "next/link";
import { megaMenuCategories } from "@/lib/home-data";
import { ArrowRightIcon, ChevronDownIcon } from "./icons";

interface MegaMenuProps {
  mobile?: boolean;
  onNavigate?: () => void;
}

export function MegaMenu({ mobile = false, onNavigate }: MegaMenuProps) {
  const [activeSlug, setActiveSlug] = useState(megaMenuCategories[0].slug);
  const [open, setOpen] = useState(false);

  const activeCategory =
    megaMenuCategories.find((c) => c.slug === activeSlug) ?? megaMenuCategories[0];

  if (mobile) {
    return (
      <div className="space-y-1">
        <button
          type="button"
          onClick={() => setOpen((v) => !v)}
          className="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-100"
          aria-expanded={open}
        >
          Categories
          <ChevronDownIcon
            className={`h-4 w-4 transition ${open ? "rotate-180" : ""}`}
          />
        </button>
        {open && (
          <div className="ml-2 space-y-3 border-l-2 border-[#c9a227]/20 pl-3">
            {megaMenuCategories.map((category) => (
              <div key={category.slug}>
                <Link
                  href="#categories"
                  onClick={onNavigate}
                  className="flex items-center gap-2 text-sm font-semibold text-zinc-900"
                >
                  <span>{category.icon}</span>
                  {category.name}
                </Link>
                <ul className="mt-1.5 space-y-1 pl-7">
                  {category.subcategories.slice(0, 4).map((sub) => (
                    <li key={sub}>
                      <Link
                        href="#products"
                        onClick={onNavigate}
                        className="text-xs text-zinc-500 hover:text-[#c9a227]"
                      >
                        {sub}
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        )}
      </div>
    );
  }

  return (
    <div
      className="group relative"
      onMouseEnter={() => setOpen(true)}
      onMouseLeave={() => setOpen(false)}
    >
      <button
        type="button"
        className="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900"
        aria-expanded={open}
        aria-haspopup="true"
      >
        Categories
        <ChevronDownIcon className="h-4 w-4 transition group-hover:rotate-180" />
      </button>

      <div
        className={`absolute left-1/2 top-full z-50 w-[720px] -translate-x-1/2 pt-2 transition-all duration-200 ${
          open
            ? "pointer-events-auto visible translate-y-0 opacity-100"
            : "pointer-events-none invisible -translate-y-2 opacity-0"
        }`}
      >
        <div className="overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-2xl shadow-zinc-900/10 ring-1 ring-zinc-900/5">
          <div className="grid grid-cols-[220px_1fr]">
            <div className="border-r border-zinc-100 bg-zinc-50/80 p-2">
              {megaMenuCategories.map((category) => (
                <button
                  key={category.slug}
                  type="button"
                  onMouseEnter={() => setActiveSlug(category.slug)}
                  className={`flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-left text-sm transition ${
                    activeSlug === category.slug
                      ? "bg-white font-semibold text-zinc-900 shadow-sm ring-1 ring-zinc-200/80"
                      : "text-zinc-600 hover:bg-white/60 hover:text-zinc-900"
                  }`}
                >
                  <span className="text-base">{category.icon}</span>
                  <span className="truncate">{category.name}</span>
                </button>
              ))}
              <Link
                href="#categories"
                className="mt-2 flex items-center gap-1 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-[#c9a227] hover:text-[#8b6914]"
              >
                View all
                <ArrowRightIcon className="h-3 w-3" />
              </Link>
            </div>

            <div className="p-6">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <h3 className="text-lg font-bold text-zinc-900">{activeCategory.name}</h3>
                  <p className="mt-1 text-sm text-zinc-500">{activeCategory.description}</p>
                </div>
                <Link
                  href="#products"
                  className="inline-flex shrink-0 items-center gap-1 rounded-full bg-zinc-900 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white transition hover:bg-[#c9a227] hover:text-zinc-900"
                >
                  Shop now
                  <ArrowRightIcon className="h-3 w-3" />
                </Link>
              </div>

              <div className="mt-5 grid grid-cols-2 gap-x-6 gap-y-2">
                {activeCategory.subcategories.map((sub) => (
                  <Link
                    key={sub}
                    href="#products"
                    className="group/sub flex items-center gap-2 rounded-lg py-1.5 text-sm text-zinc-600 transition hover:text-[#c9a227]"
                  >
                    <span className="h-1 w-1 rounded-full bg-zinc-300 transition group-hover/sub:bg-[#c9a227]" />
                    {sub}
                  </Link>
                ))}
              </div>

              <div
                className={`mt-6 flex items-center gap-4 rounded-xl bg-gradient-to-r ${activeCategory.gradient} p-4`}
              >
                <span className="text-3xl">{activeCategory.icon}</span>
                <div className="min-w-0 flex-1">
                  <p className="text-xs font-semibold uppercase tracking-wider text-white/80">
                    Featured
                  </p>
                  <p className="truncate text-sm font-bold text-white">
                    {activeCategory.featured}
                  </p>
                </div>
                <Link
                  href="#products"
                  className="shrink-0 rounded-full bg-white/20 px-3 py-1.5 text-xs font-bold text-white backdrop-blur transition hover:bg-white/30"
                >
                  Explore
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
